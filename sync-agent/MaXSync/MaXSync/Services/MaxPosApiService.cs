using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using System.Text.Json.Serialization;
using MaXSync.Models;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace MaXSync.Services;

// Client HTTP catre MaXPos. Reautentifica automat la 401.
public sealed class MaxPosApiService
{
    private readonly HttpClient _http;
    private readonly MaxPosOptions _options;
    private readonly ILogger<MaxPosApiService> _logger;
    private readonly SemaphoreSlim _authLock = new(1, 1);
    private string? _token;

    private static readonly JsonSerializerOptions JsonOpts = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };

    public MaxPosApiService(HttpClient http, IOptions<MaxPosOptions> options, ILogger<MaxPosApiService> logger)
    {
        _http = http;
        _options = options.Value;
        _logger = logger;
        _http.BaseAddress = new Uri(_options.BaseUrl.TrimEnd('/') + "/");
	_http.DefaultRequestHeaders.Accept.ParseAdd("application/json");
        _http.Timeout = TimeSpan.FromSeconds(60);
    }

    public async Task EnsureAuthenticatedAsync(CancellationToken ct)
    {
        if (!string.IsNullOrEmpty(_token)) return;
        await AuthenticateAsync(ct);
    }

    private async Task AuthenticateAsync(CancellationToken ct)
    {
        await _authLock.WaitAsync(ct);
        try
        {
            var payload = new { email = _options.Email, password = _options.Password };
            using var resp = await _http.PostAsJsonAsync("api/v1/auth/login", payload, JsonOpts, ct);
            resp.EnsureSuccessStatusCode();
            var body = await resp.Content.ReadFromJsonAsync<LoginResponse>(JsonOpts, ct)
                ?? throw new InvalidOperationException("Raspuns gol de la endpointul de login.");
            _token = body.Token ?? body.Data?.Token
                ?? throw new InvalidOperationException("Tokenul lipseste din raspunsul de login.");
            _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", _token);
            _logger.LogInformation("Autentificat la MaXPos ca {Email}", _options.Email);
        }
        finally
        {
            _authLock.Release();
        }
    }

    public async Task PushArticlesAsync(
        IReadOnlyList<MaxPosArticle> articles,
        IReadOnlyList<SagaGroup> groups,
        IReadOnlyList<SagaGestiune> gestiuni,
        CancellationToken ct)
    {
        var payload = new
        {
            articles,
            groups = groups.Select(g => new { code = g.Cod, name = g.Denumire }).ToArray(),
            gestiuni = gestiuni.Select(g => new { code = g.Cod, name = g.Denumire }).ToArray(),
        };
var jsonPreview = System.Text.Json.JsonSerializer.Serialize(
    articles.FirstOrDefault(), JsonOpts);
_logger.LogInformation("First article JSON: {Json}", jsonPreview);

        await SendWithReauthAsync(
            () => new HttpRequestMessage(HttpMethod.Post, "api/v1/sync/articles")
            {
                Content = JsonContent.Create(payload, options: JsonOpts),
            },
            ct);
    }

    public async Task<List<MaxPosReceipt>> GetPendingReceiptsAsync(CancellationToken ct)
    {
        using var resp = await SendWithReauthAsync(
            () => new HttpRequestMessage(HttpMethod.Get, "api/v1/sync/receipts/pending"),
            ct);
        var body = await resp.Content.ReadFromJsonAsync<PendingReceiptsResponse>(JsonOpts, ct);
        return body?.Data ?? new List<MaxPosReceipt>();
    }

    public async Task MarkReceiptSyncedAsync(string receiptId, CancellationToken ct)
    {
        using var resp = await SendWithReauthAsync(
            () => new HttpRequestMessage(HttpMethod.Post, $"api/v1/sync/receipts/{receiptId}/mark-synced"),
            ct);
        _ = resp;
    }

    private async Task<HttpResponseMessage> SendWithReauthAsync(
        Func<HttpRequestMessage> requestFactory,
        CancellationToken ct)
    {
        await EnsureAuthenticatedAsync(ct);

        var request = requestFactory();
        var response = await _http.SendAsync(request, ct);
        if (response.StatusCode == HttpStatusCode.Unauthorized)
        {
            response.Dispose();
            _logger.LogWarning("MaXPos a raspuns 401, reautentificare...");
            _token = null;
            await AuthenticateAsync(ct);
            request = requestFactory();
            response = await _http.SendAsync(request, ct);
        }
        response.EnsureSuccessStatusCode();
        return response;
    }

    private sealed class LoginResponse
    {
        [JsonPropertyName("token")] public string? Token { get; set; }
        [JsonPropertyName("data")] public LoginData? Data { get; set; }
    }

    private sealed class LoginData
    {
        [JsonPropertyName("token")] public string? Token { get; set; }
    }

    private sealed class PendingReceiptsResponse
    {
        [JsonPropertyName("data")] public List<MaxPosReceipt>? Data { get; set; }
    }
}
