using System.Text.Json.Serialization;

namespace MaXSync.Models;

// Bon fiscal primit din MaXPos pentru export catre Saga.
public sealed class MaxPosReceipt
{
    [JsonPropertyName("id")]
    public long Id { get; set; }

    [JsonPropertyName("number")]
    public string Number { get; set; } = string.Empty;

    [JsonPropertyName("issued_at")]
    public DateTime IssuedAt { get; set; }

    [JsonPropertyName("customer_code")]
    public string? CustomerCode { get; set; }

    [JsonPropertyName("customer_name")]
    public string? CustomerName { get; set; }

    [JsonPropertyName("gestiune_code")]
    public string GestiuneCode { get; set; } = string.Empty;

    [JsonPropertyName("gestiune_name")]
    public string GestiuneName { get; set; } = string.Empty;

    [JsonPropertyName("subtotal")]
    public decimal Subtotal { get; set; }

    [JsonPropertyName("vat_total")]
    public decimal VatTotal { get; set; }

    [JsonPropertyName("total")]
    public decimal Total { get; set; }

    [JsonPropertyName("items")]
    public List<MaxPosReceiptItem> Items { get; set; } = new();
}
