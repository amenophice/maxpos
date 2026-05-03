using MaXSync;
using MaXSync.Services;
using Serilog;

var builder = Host.CreateApplicationBuilder(args);

builder.Services.AddWindowsService(o => o.ServiceName = "MaXSync");

builder.Services.AddSerilog((sp, lc) => lc
    .ReadFrom.Configuration(builder.Configuration)
    .Enrich.FromLogContext());

builder.Services.AddOptions<MaxPosOptions>()
    .Bind(builder.Configuration.GetSection("MaxPos"));

builder.Services.AddOptions<FirebirdOptions>()
    .Bind(builder.Configuration.GetSection("Firebird"));

builder.Services.AddSingleton<FirebirdService>();
builder.Services.AddSingleton<SyncStateStore>();
builder.Services.AddSingleton<ArticleSyncService>();
builder.Services.AddSingleton<ReceiptExportService>();

builder.Services.AddHttpClient<MaxPosApiService>();

builder.Services.AddHostedService<Worker>();

var host = builder.Build();
host.Run();
