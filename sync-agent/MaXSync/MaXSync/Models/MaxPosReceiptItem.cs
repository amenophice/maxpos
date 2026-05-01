using System.Text.Json.Serialization;

namespace MaXSync.Models;

// Linie a unui bon fiscal primit din MaXPos.
public sealed class MaxPosReceiptItem
{
    [JsonPropertyName("sku")]
    public string Sku { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("unit")]
    public string Unit { get; set; } = string.Empty;

    [JsonPropertyName("vat_rate")]
    public decimal VatRate { get; set; }

    [JsonPropertyName("quantity")]
    public decimal Quantity { get; set; }

    [JsonPropertyName("unit_price_ex_vat")]
    public decimal UnitPriceExVat { get; set; }

    [JsonPropertyName("unit_price_inc_vat")]
    public decimal UnitPriceIncVat { get; set; }

    [JsonPropertyName("line_total_ex_vat")]
    public decimal LineTotalExVat { get; set; }

    [JsonPropertyName("line_vat")]
    public decimal LineVat { get; set; }

    [JsonPropertyName("line_total_inc_vat")]
    public decimal LineTotalIncVat { get; set; }
}
