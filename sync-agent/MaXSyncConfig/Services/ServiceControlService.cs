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
}
