using System.IO;
using System.Text.Json;
using MaXSyncConfig.Models;

namespace MaXSyncConfig.Services;

public sealed class ConfigService
{
    private static readonly JsonSerializerOptions ReadOpts = new()
    {
        PropertyNameCaseInsensitive = true,
        ReadCommentHandling = JsonCommentHandling.Skip,
        AllowTrailingCommas = true,
    };

    private static readonly JsonSerializerOptions WriteOpts = new()
    {
        WriteIndented = true,
    };

    // Cauta appsettings.json in locatii cunoscute. Returneaza null daca nu gaseste.
    public string? FindDefaultPath()
    {
        var exeDir = AppContext.BaseDirectory;
        var candidates = new[]
        {
            Path.Combine(exeDir, "appsettings.json"),
            Path.GetFullPath(Path.Combine(exeDir, "..", "MaXSync", "appsettings.json")),
            Path.GetFullPath(Path.Combine(exeDir, "..", "..", "MaXSync", "MaXSync", "appsettings.json")),
            Path.GetFullPath(Path.Combine(exeDir, "..", "..", "..", "..", "MaXSync", "MaXSync", "appsettings.json")),
        };

        foreach (var c in candidates)
        {
            if (File.Exists(c)) return c;
        }
        return null;
    }

    public AppSettings Load(string path)
    {
        if (!File.Exists(path)) return new AppSettings();
        var json = File.ReadAllText(path);
        return JsonSerializer.Deserialize<AppSettings>(json, ReadOpts) ?? new AppSettings();
    }

    public void Save(string path, AppSettings settings)
    {
        var json = JsonSerializer.Serialize(settings, WriteOpts);
        File.WriteAllText(path, json);
    }
}
