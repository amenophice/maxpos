namespace MaXSync.Services;
public sealed class MaxPosOptions
{
    public string BaseUrl { get; set; } = "https://api.maxpos.ro";
    public string Email { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public int ArticleSyncIntervalMinutes { get; set; } = 30;
    public int ReceiptExportIntervalMinutes { get; set; } = 5;

    // Prefix pentru NR_IESIRE in Saga (ex: "POS1" → "POS1-0001")
    public string ReceiptNumberPrefix { get; set; } = "MXPS";

    // Client implicit pentru vanzari anonime
    public string DefaultClientCode { get; set; } = "DIVERSE";
    public string DefaultClientName { get; set; } = "CLIENT DIVERSE";

    // Gestiunea implicita pentru bonuri (cand articolul nu are gestiune)
    public string DefaultGestiuneCode { get; set; } = "0001";
    public string DefaultGestiuneName { get; set; } = "MARFURI EN-GROSS";

    // Sincronizeaza doar articole active (BLOCAT = 0)
    public bool SyncOnlyActiveArticles { get; set; } = true;
}