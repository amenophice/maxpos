using MaXSync.Services;
using Microsoft.Extensions.Options;

namespace MaXSync;

// Bucla principala a serviciului. Doua planificatori independenti pentru
// articole si bonuri; orice eroare este logata si bucla continua.
public sealed class Worker : BackgroundService
{
    private readonly ArticleSyncService _articleSync;
    private readonly ReceiptExportService _receiptExport;
    private readonly MaxPosApiService _api;
    private readonly ILogger<Worker> _logger;
    private readonly TimeSpan _articleInterval;
    private readonly TimeSpan _receiptInterval;

    public Worker(
        ArticleSyncService articleSync,
        ReceiptExportService receiptExport,
        MaxPosApiService api,
        IOptions<MaxPosOptions> options,
        ILogger<Worker> logger)
    {
        _articleSync = articleSync;
        _receiptExport = receiptExport;
        _api = api;
        _logger = logger;
        _articleInterval = TimeSpan.FromMinutes(Math.Max(1, options.Value.ArticleSyncIntervalMinutes));
        _receiptInterval = TimeSpan.FromMinutes(Math.Max(1, options.Value.ReceiptExportIntervalMinutes));
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation(
            "MaXSync porneste. Articole la {Articles}, bonuri la {Receipts}.",
            _articleInterval, _receiptInterval);

        try
        {
            await _api.EnsureAuthenticatedAsync(stoppingToken);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Autentificare initiala esuata; voi reincerca la primul ciclu.");
        }

        var articleLoop = RunLoopAsync("articles", _articleInterval, _articleSync.RunAsync, stoppingToken);
        var receiptLoop = RunLoopAsync("receipts", _receiptInterval, _receiptExport.RunAsync, stoppingToken);

        await Task.WhenAll(articleLoop, receiptLoop);
    }

    private async Task RunLoopAsync(
        string name,
        TimeSpan interval,
        Func<CancellationToken, Task> action,
        CancellationToken stoppingToken)
    {
        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await action(stoppingToken);
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Eroare in bucla {Loop}; continui dupa pauza.", name);
            }

            try
            {
                await Task.Delay(interval, stoppingToken);
            }
            catch (OperationCanceledException)
            {
                break;
            }
        }
    }
}
