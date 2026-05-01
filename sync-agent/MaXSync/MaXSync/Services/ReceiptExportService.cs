using Microsoft.Extensions.Logging;

namespace MaXSync.Services;

// Export bonuri: MaXPos -> Saga (IESIRI/IES_DET).
public sealed class ReceiptExportService
{
    private readonly FirebirdService _firebird;
    private readonly MaxPosApiService _api;
    private readonly SyncStateStore _state;
    private readonly ILogger<ReceiptExportService> _logger;

    public ReceiptExportService(
        FirebirdService firebird,
        MaxPosApiService api,
        SyncStateStore state,
        ILogger<ReceiptExportService> logger)
    {
        _firebird = firebird;
        _api = api;
        _state = state;
        _logger = logger;
    }

    public async Task RunAsync(CancellationToken ct)
    {
        var pending = await _api.GetPendingReceiptsAsync(ct);
        if (pending.Count == 0)
        {
            _logger.LogDebug("Nu sunt bonuri de exportat.");
            return;
        }

        _logger.LogInformation("Export {Count} bonuri catre Saga...", pending.Count);

        var ok = 0;
        var failed = 0;
        long? lastId = null;

        foreach (var receipt in pending)
        {
            ct.ThrowIfCancellationRequested();
            try
            {
                await _firebird.InsertReceiptAsync(receipt, ct);
                await _api.MarkReceiptSyncedAsync(receipt.Id, ct);
                ok++;
                lastId = receipt.Id;
            }
            catch (Exception ex)
            {
                failed++;
                _logger.LogError(ex,
                    "Eroare la export bonul {Number} (id={Id}); trec mai departe.",
                    receipt.Number, receipt.Id);
            }
        }

        var state = await _state.LoadAsync(ct);
        state.ReceiptsLastExportAt = DateTime.UtcNow;
        if (lastId.HasValue) state.LastExportedReceiptId = lastId.Value;
        await _state.SaveAsync(state, ct);

        _logger.LogInformation("Export terminat: {Ok} reusite, {Failed} esuate.", ok, failed);
    }
}
