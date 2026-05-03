namespace MaXSync.Models;

// Reprezinta o linie din tabela ARTICOLE (Saga v602).
public sealed class SagaArticle
{
    public string Cod { get; set; } = string.Empty;
    public string Denumire { get; set; } = string.Empty;
    public string Um { get; set; } = string.Empty;
    public decimal Tva { get; set; }
    public decimal PretVTva { get; set; }
    public decimal? Plu { get; set; }
    public decimal? CodBare { get; set; }
    public string? Grupa { get; set; }
    public short Blocat { get; set; }
}
