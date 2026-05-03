using MaXSync.Models;
using Microsoft.Extensions.Logging;

namespace MaXSync.Services;

// Sincronizare nomenclator: Saga (ARTICOLE/ART_GR/GESTIUNI) -> MaXPos.
public sealed class ArticleSyncService
{
    private readonly FirebirdService _firebird;
    private readonly MaxPosApiService _api;
    private readonly SyncStateStore _state;
    private readonly ILogger<ArticleSyncService> _logger;

    public ArticleSyncService(
        FirebirdService firebird,
        MaxPosApiService api,
        SyncStateStore state,
        ILogger<ArticleSyncService> logger)
    {
        _firebird = firebird;
        _api = api;
        _state = state;
        _logger = logger;
    }

    public async Task RunAsync(CancellationToken ct)
    {
        _logger.LogInformation("Pornesc sincronizarea articolelor...");

        var articlesTask = _firebird.GetActiveArticlesAsync(ct);
        var groupsTask = _firebird.GetGroupsAsync(ct);
        var gestiuniTask = _firebird.GetGestiuniAsync(ct);
        await Task.WhenAll(articlesTask, groupsTask, gestiuniTask);

        var sagaArticles = await articlesTask;
        var groups = await groupsTask;
        var gestiuni = await gestiuniTask;

        var maxPosArticles = sagaArticles.Select(Map).ToList();

var distinctSkus = maxPosArticles.Select(a => a.Sku).Distinct().Count();
var emptySkus = maxPosArticles.Count(a => string.IsNullOrEmpty(a.Sku));
_logger.LogInformation(
    "Articles: {Total} total, {Distinct} SKU-uri distincte, {Empty} fara SKU",
    maxPosArticles.Count, distinctSkus, emptySkus);
_logger.LogInformation("Sample SKUs: {Skus}", 
    string.Join(", ", maxPosArticles.Take(5).Select(a => a.Sku)));

        await _api.PushArticlesAsync(maxPosArticles, groups, gestiuni, ct);

        var state = await _state.LoadAsync(ct);
        state.ArticlesLastSyncAt = DateTime.UtcNow;
        await _state.SaveAsync(state, ct);

        _logger.LogInformation(
            "Articole sincronizate: {Articles} articole, {Groups} grupe, {Gestiuni} gestiuni",
            maxPosArticles.Count, groups.Count, gestiuni.Count);
    }

    private static MaxPosArticle Map(SagaArticle a) => new()
    {
        Sku = a.Cod,
        Name = a.Denumire,
        Unit = a.Um,
        VatRate = a.Tva,
        PriceWithVat = a.PretVTva,
        Plu = a.Plu.HasValue && a.Plu.Value > 0 ? (long)a.Plu.Value : null,
        Barcode = a.CodBare.HasValue && a.CodBare.Value > 0
            ? a.CodBare.Value.ToString("F0", System.Globalization.CultureInfo.InvariantCulture)
            : null,
        GroupCode = a.Grupa,
        Active = a.Blocat == 0,
    };
}
