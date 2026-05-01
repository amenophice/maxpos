using System.Text.Json.Serialization;

namespace MaXSync.Models;

// Stare persistenta a agentului, salvata in state.json langa executabil.
public sealed class SyncState
{
    [JsonPropertyName("articles_last_sync_at")]
    public DateTime? ArticlesLastSyncAt { get; set; }

    [JsonPropertyName("receipts_last_export_at")]
    public DateTime? ReceiptsLastExportAt { get; set; }

    [JsonPropertyName("last_exported_receipt_id")]
    public long? LastExportedReceiptId { get; set; }
}
