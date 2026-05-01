using System.Text.Json.Serialization;

namespace MaXSync.Models;

// Articol pregatit pentru POST /api/v1/sync/articles.
public sealed class MaxPosArticle
{
    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = string.Empty;

    [JsonPropertyName("vat_rate")]
    public decimal VatRate { get; set; }

    [JsonPropertyName("price_with_vat")]
    public decimal PriceWithVat { get; set; }

    [JsonPropertyName("plu")]
    public long? Plu { get; set; }

    [JsonPropertyName("barcode")]
    public string? Barcode { get; set; }

    [JsonPropertyName("group_code")]
    public string? GroupCode { get; set; }

    [JsonPropertyName("active")]
    public bool Active { get; set; } = true;
}
