using System.Text.Json.Serialization;

namespace MaXSyncConfig.Models;

// Oglinda fisierului appsettings.json folosit de MaXSync.
public sealed class AppSettings
{
    [JsonPropertyName("MaxPos")]
    public MaxPosSection MaxPos { get; set; } = new();

    [JsonPropertyName("Firebird")]
    public FirebirdSection Firebird { get; set; } = new();

    // Pastreaza orice cheie suplimentara (ex: Serilog) pentru a nu o pierde la salvare.
    [JsonExtensionData]
    public Dictionary<string, System.Text.Json.JsonElement> Extra { get; set; } = new();
}

public sealed class MaxPosSection
{
    [JsonPropertyName("BaseUrl")]
    public string BaseUrl { get; set; } = "https://api.maxpos.ro";

    [JsonPropertyName("Email")]
    public string Email { get; set; } = string.Empty;

    [JsonPropertyName("Password")]
    public string Password { get; set; } = string.Empty;

    [JsonPropertyName("ArticleSyncIntervalMinutes")]
    public int ArticleSyncIntervalMinutes { get; set; } = 30;

    [JsonPropertyName("ReceiptExportIntervalMinutes")]
    public int ReceiptExportIntervalMinutes { get; set; } = 5;

    [JsonPropertyName("ReceiptNumberPrefix")]
    public string ReceiptNumberPrefix { get; set; } = "MXPS";

    [JsonPropertyName("DefaultClientCode")]
    public string DefaultClientCode { get; set; } = "DIVERSE";

    [JsonPropertyName("DefaultClientName")]
    public string DefaultClientName { get; set; } = "Clienti diversi";

    [JsonPropertyName("DefaultGestiuneCode")]
    public string DefaultGestiuneCode { get; set; } = "0001";

    [JsonPropertyName("DefaultGestiuneName")]
    public string DefaultGestiuneName { get; set; } = "Magazin";

    [JsonPropertyName("SyncOnlyActiveArticles")]
    public bool SyncOnlyActiveArticles { get; set; } = true;
}

public sealed class FirebirdSection
{
    [JsonPropertyName("Host")]
    public string Host { get; set; } = "localhost";

    [JsonPropertyName("Port")]
    public int Port { get; set; } = 3050;

    [JsonPropertyName("Database")]
    public string Database { get; set; } = string.Empty;

    [JsonPropertyName("Username")]
    public string Username { get; set; } = "SYSDBA";

    [JsonPropertyName("Password")]
    public string Password { get; set; } = "masterkey";

    [JsonPropertyName("Charset")]
    public string Charset { get; set; } = "UTF8";
}
