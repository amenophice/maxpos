using System.Text.Json;
using MaXSync.Models;
using Microsoft.Extensions.Logging;

namespace MaXSync.Services;

// Citeste / scrie state.json langa executabil.
public sealed class SyncStateStore
{
    private readonly string _path;
    private readonly ILogger<SyncStateStore> _logger;
    private readonly SemaphoreSlim _lock = new(1, 1);

    private static readonly JsonSerializerOptions JsonOpts = new() { WriteIndented = true };

    public SyncStateStore(ILogger<SyncStateStore> logger)
    {
        _logger = logger;
        var baseDir = AppContext.BaseDirectory;
        _path = Path.Combine(baseDir, "state.json");
    }

    public async Task<SyncState> LoadAsync(CancellationToken ct)
    {
        await _lock.WaitAsync(ct);
        try
        {
            if (!File.Exists(_path)) return new SyncState();
            await using var fs = File.OpenRead(_path);
            return await JsonSerializer.DeserializeAsync<SyncState>(fs, JsonOpts, ct) ?? new SyncState();
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Nu am putut citi state.json, pornesc cu stare goala.");
            return new SyncState();
        }
        finally
        {
            _lock.Release();
        }
    }

    public async Task SaveAsync(SyncState state, CancellationToken ct)
    {
        await _lock.WaitAsync(ct);
        try
        {
            await using var fs = File.Create(_path);
            await JsonSerializer.SerializeAsync(fs, state, JsonOpts, ct);
        }
        finally
        {
            _lock.Release();
        }
    }
}
