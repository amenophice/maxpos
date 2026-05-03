using System.ComponentModel;
using System.Diagnostics;
using System.ServiceProcess;

namespace MaXSyncConfig.Services;

public enum ServiceState
{
    NotInstalled,
    Stopped,
    Running,
    StartPending,
    StopPending,
    Other,
}

public sealed class ServiceControlService
{
    public const string ServiceName = "MaXSync";

    public ServiceState GetState()
    {
        try
        {
            using var sc = new ServiceController(ServiceName);
            return sc.Status switch
            {
                ServiceControllerStatus.Running => ServiceState.Running,
                ServiceControllerStatus.Stopped => ServiceState.Stopped,
                ServiceControllerStatus.StartPending => ServiceState.StartPending,
                ServiceControllerStatus.StopPending => ServiceState.StopPending,
                _ => ServiceState.Other,
            };
        }
        catch (InvalidOperationException)
        {
            return ServiceState.NotInstalled;
        }
    }

    public void Start(TimeSpan? timeout = null)
    {
        using var sc = new ServiceController(ServiceName);
        if (sc.Status == ServiceControllerStatus.Running) return;
        sc.Start();
        sc.WaitForStatus(ServiceControllerStatus.Running, timeout ?? TimeSpan.FromSeconds(30));
    }

    public void Stop(TimeSpan? timeout = null)
    {
        using var sc = new ServiceController(ServiceName);
        if (sc.Status == ServiceControllerStatus.Stopped) return;
        sc.Stop();
        sc.WaitForStatus(ServiceControllerStatus.Stopped, timeout ?? TimeSpan.FromSeconds(30));
    }

    public void Restart(TimeSpan? timeout = null)
    {
        var t = timeout ?? TimeSpan.FromSeconds(30);
        using var sc = new ServiceController(ServiceName);
        if (sc.Status != ServiceControllerStatus.Stopped)
        {
            sc.Stop();
            sc.WaitForStatus(ServiceControllerStatus.Stopped, t);
        }
        sc.Start();
        sc.WaitForStatus(ServiceControllerStatus.Running, t);
    }

    public sealed class ElevationCancelledException : Exception
    {
        public ElevationCancelledException() : base("Operațiunea necesită drepturi de Administrator.") { }
    }

    public sealed class ScCommandException : Exception
    {
        public int ExitCode { get; }
        public ScCommandException(int exitCode, string message) : base(message)
        {
            ExitCode = exitCode;
        }
    }

    public void InstallService(string exePath, string displayName, string description)
    {
        // sc.exe asteapta sintaxa "key= value" (cu spatiu DUPA semnul egal).
        var binPathArg = $"binPath= \"\\\"{exePath}\\\"\"";
        var displayArg = $"DisplayName= \"{displayName}\"";
        RunSc($"create {ServiceName} {binPathArg} {displayArg} start= auto");
        RunSc($"description {ServiceName} \"{description}\"");
    }

    public void UninstallService()
    {
        // Daca ruleaza, opreste-l intai (ignora orice eroare).
        try { RunSc($"stop {ServiceName}"); } catch { /* poate sa fie deja oprit */ }
        RunSc($"delete {ServiceName}");
    }

    private static void RunSc(string arguments)
    {
        var psi = new ProcessStartInfo
        {
            FileName = "sc.exe",
            Arguments = arguments,
            UseShellExecute = true,
            Verb = "runas",
            CreateNoWindow = true,
            WindowStyle = ProcessWindowStyle.Hidden,
        };

        Process? p;
        try
        {
            p = Process.Start(psi);
        }
        catch (Win32Exception ex) when (ex.NativeErrorCode == 1223)
        {
            // ERROR_CANCELLED — utilizatorul a respins promptul UAC.
            throw new ElevationCancelledException();
        }

        if (p is null) throw new InvalidOperationException("Nu am putut porni sc.exe.");
        p.WaitForExit();

        if (p.ExitCode != 0)
        {
            throw new ScCommandException(p.ExitCode,
                $"sc.exe {arguments.Split(' ')[0]} a esuat cu codul {p.ExitCode}.");
        }
    }
}
