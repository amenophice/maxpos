using FirebirdSql.Data.FirebirdClient;
using MaXSync.Helpers;
using MaXSync.Models;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace MaXSync.Services;

// Toate operatiile directe pe baza Firebird a Saga.
public sealed class FirebirdService
{
    private readonly FirebirdOptions _options;
    private readonly ILogger<FirebirdService> _logger;

    public FirebirdService(IOptions<FirebirdOptions> options, ILogger<FirebirdService> logger)
    {
        _options = options.Value;
        _logger = logger;
    }

    private FbConnection OpenConnection()
    {
        var conn = new FbConnection(_options.BuildConnectionString());
        conn.Open();
        return conn;
    }

    public async Task<List<SagaArticle>> GetActiveArticlesAsync(CancellationToken ct)
    {
        const string sql = @"
            SELECT COD, DENUMIRE, UM, TVA, PRET_V_TVA, PLU, COD_BARE, GRUPA, BLOCAT
            FROM ARTICOLE
            WHERE BLOCAT = 0";

        var result = new List<SagaArticle>();
        await using var conn = OpenConnection();
        await using var cmd = new FbCommand(sql, conn);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            result.Add(new SagaArticle
            {
                Cod = DecimalHelper.ToTrimmedString(reader["COD"]),
                Denumire = DecimalHelper.ToTrimmedString(reader["DENUMIRE"]),
                Um = DecimalHelper.ToTrimmedString(reader["UM"]),
                Tva = DecimalHelper.ToDecimal(reader["TVA"]),
                PretVTva = DecimalHelper.ToDecimal(reader["PRET_V_TVA"]),
                Plu = DecimalHelper.ToNullableDecimal(reader["PLU"]),
                CodBare = DecimalHelper.ToNullableDecimal(reader["COD_BARE"]),
                Grupa = DecimalHelper.ToTrimmedNullableString(reader["GRUPA"]),
                Blocat = DecimalHelper.ToShort(reader["BLOCAT"]),
            });
        }
        return result;
    }

    public async Task<List<SagaGroup>> GetGroupsAsync(CancellationToken ct)
    {
        const string sql = "SELECT COD, DENUMIRE FROM ART_GR";
        var result = new List<SagaGroup>();
        await using var conn = OpenConnection();
        await using var cmd = new FbCommand(sql, conn);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            result.Add(new SagaGroup
            {
                Cod = DecimalHelper.ToTrimmedString(reader["COD"]),
                Denumire = DecimalHelper.ToTrimmedString(reader["DENUMIRE"]),
            });
        }
        return result;
    }

    public async Task<List<SagaGestiune>> GetGestiuniAsync(CancellationToken ct)
    {
        const string sql = "SELECT COD, DENUMIRE FROM GESTIUNI";
        var result = new List<SagaGestiune>();
        await using var conn = OpenConnection();
        await using var cmd = new FbCommand(sql, conn);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            result.Add(new SagaGestiune
            {
                Cod = DecimalHelper.ToTrimmedString(reader["COD"]),
                Denumire = DecimalHelper.ToTrimmedString(reader["DENUMIRE"]),
            });
        }
        return result;
    }

    // Insereaza un bon fiscal complet (header + linii) intr-o singura tranzactie.
    public async Task InsertReceiptAsync(MaxPosReceipt receipt, CancellationToken ct)
    {
        await using var conn = OpenConnection();
        await using var tx = await conn.BeginTransactionAsync(ct);
        try
        {
            // TODO: verifica numele real al generatorului in Saga v602.
            const string nextIdSql = "SELECT GEN_ID(GEN_IESIRI_ID, 1) AS NEW_ID FROM RDB$DATABASE";
            long newIesireId;
            await using (var cmd = new FbCommand(nextIdSql, conn, (FbTransaction)tx))
            {
                var raw = await cmd.ExecuteScalarAsync(ct);
                newIesireId = Convert.ToInt64(raw);
            }

            const string insertHeaderSql = @"
                INSERT INTO IESIRI
                    (ID_IESIRE, NR_IESIRE, COD, DENUMIRE, DATA,
                     BAZA_TVA, TVA, TOTAL, NEACHITAT, VALIDAT, TIP, NR_BONURI, TIPARIT)
                VALUES
                    (@id, @nr, @cod, @den, @data,
                     @baza, @tva, @total, 0, 'D', 'B', 1, 0)";
            await using (var cmd = new FbCommand(insertHeaderSql, conn, (FbTransaction)tx))
            {
                cmd.Parameters.AddWithValue("@id", newIesireId);
                cmd.Parameters.AddWithValue("@nr", Truncate(receipt.Number, 16));
                cmd.Parameters.AddWithValue("@cod", Truncate(receipt.CustomerCode ?? string.Empty, 8));
                cmd.Parameters.AddWithValue("@den", Truncate(receipt.CustomerName ?? string.Empty, 64));
                cmd.Parameters.AddWithValue("@data", receipt.IssuedAt.Date);
                cmd.Parameters.AddWithValue("@baza", receipt.Subtotal);
                cmd.Parameters.AddWithValue("@tva", receipt.VatTotal);
                cmd.Parameters.AddWithValue("@total", receipt.Total);
                await cmd.ExecuteNonQueryAsync(ct);
            }

            const string nextLineIdSql = "SELECT GEN_ID(GEN_IES_DET_PK, 1) AS NEW_PK FROM RDB$DATABASE";
            const string insertLineSql = @"
                INSERT INTO IES_DET
                    (PK, ID_IESIRE, GESTIUNE, DEN_GEST, COD, DENUMIRE, UM, TVA_ART,
                     CANTITATE, PRET_UNITAR, PU_TVA, VALOARE, TVA_DED, TOTAL, DISCOUNT, ADAOS)
                VALUES
                    (@pk, @id_iesire, @gest, @den_gest, @cod, @den, @um, @tva_art,
                     @cant, @pret, @pu_tva, @valoare, @tva_ded, @total, 0, 0)";

            foreach (var item in receipt.Items)
            {
                long newPk;
                // TODO: verifica numele real al generatorului in Saga v602.
                await using (var cmd = new FbCommand(nextLineIdSql, conn, (FbTransaction)tx))
                {
                    newPk = Convert.ToInt64(await cmd.ExecuteScalarAsync(ct));
                }

                await using var insertCmd = new FbCommand(insertLineSql, conn, (FbTransaction)tx);
                insertCmd.Parameters.AddWithValue("@pk", newPk);
                insertCmd.Parameters.AddWithValue("@id_iesire", newIesireId);
                insertCmd.Parameters.AddWithValue("@gest", Truncate(receipt.GestiuneCode, 4));
                insertCmd.Parameters.AddWithValue("@den_gest", Truncate(receipt.GestiuneName, 24));
                insertCmd.Parameters.AddWithValue("@cod", Truncate(item.Sku, 16));
                insertCmd.Parameters.AddWithValue("@den", Truncate(item.Name, 60));
                insertCmd.Parameters.AddWithValue("@um", Truncate(item.Unit, 5));
                insertCmd.Parameters.AddWithValue("@tva_art", (short)Math.Round(item.VatRate, MidpointRounding.AwayFromZero));
                insertCmd.Parameters.AddWithValue("@cant", item.Quantity);
                insertCmd.Parameters.AddWithValue("@pret", item.UnitPriceExVat);
                insertCmd.Parameters.AddWithValue("@pu_tva", item.UnitPriceIncVat);
                insertCmd.Parameters.AddWithValue("@valoare", item.LineTotalExVat);
                insertCmd.Parameters.AddWithValue("@tva_ded", item.LineVat);
                insertCmd.Parameters.AddWithValue("@total", item.LineTotalIncVat);
                await insertCmd.ExecuteNonQueryAsync(ct);
            }

            await tx.CommitAsync(ct);
            _logger.LogInformation(
                "Bon {Number} inserat in Saga ca ID_IESIRE={IesireId}, {Lines} linii",
                receipt.Number, newIesireId, receipt.Items.Count);
        }
        catch
        {
            await tx.RollbackAsync(ct);
            throw;
        }
    }

    private static string Truncate(string value, int maxLength)
        => string.IsNullOrEmpty(value) || value.Length <= maxLength ? value : value[..maxLength];
}
