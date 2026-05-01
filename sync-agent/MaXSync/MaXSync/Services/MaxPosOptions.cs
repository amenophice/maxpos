namespace MaXSync.Services;

public sealed class MaxPosOptions
{
    public string BaseUrl { get; set; } = "https://api.maxpos.ro";
    public string Email { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public int ArticleSyncIntervalMinutes { get; set; } = 30;
    public int ReceiptExportIntervalMinutes { get; set; } = 5;
}
