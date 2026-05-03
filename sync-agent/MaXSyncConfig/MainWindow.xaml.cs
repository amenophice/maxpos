using System.Globalization;
using System.IO;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;
using System.Threading;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Threading;
using FirebirdSql.Data.FirebirdClient;
using MaXSyncConfig.Models;
using MaXSyncConfig.Services;
using Microsoft.Win32;

namespace MaXSyncConfig;

public partial class MainWindow : Window
{
    private readonly ConfigService _configService = new();
    private readonly ServiceControlService _serviceControl = new();
    private readonly DispatcherTimer _statusTimer;

    private string? _configPath;
    private AppSettings _settings = new();

    private static readonly SolidColorBrush DotGreen = new(Color.FromRgb(0x3A, 0x7A, 0x3A));
    private static readonly SolidColorBrush DotRed = new(Color.FromRgb(0xB5, 0x44, 0x3A));
    private static readonly SolidColorBrush DotOrange = new(Color.FromRgb(0xC9, 0x7A, 0x2A));
    private static readonly SolidColorBrush DotGrey = new(Color.FromRgb(0x88, 0x88, 0x88));

    public MainWindow()
    {
        InitializeComponent();
        Loaded += OnLoaded;

        _statusTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(3) };
        _statusTimer.Tick += (_, _) => RefreshServiceStatus();
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        _configPath = _configService.FindDefaultPath();
        if (_configPath is null)
        {
            var picked = PromptForConfigPath();
            if (picked is null)
            {
                Application.Current.Shutdown();
                return;
            }
            _configPath = picked;
        }

        try
        {
            _settings = _configService.Load(_configPath);
        }
        catch (Exception ex)
        {
            MessageBox.Show(this,
                $"Nu pot citi {_configPath}:\n\n{ex.Message}",
                "Eroare configurație",
                MessageBoxButton.OK, MessageBoxImage.Error);
            _settings = new AppSettings();
        }

        BindToUi();
        UpdateConfigPathLabel();
        RefreshServiceStatus();
        _statusTimer.Start();
    }

    private string? PromptForConfigPath()
    {
        MessageBox.Show(this,
            "Nu am găsit appsettings.json. Te rog selectează manual fișierul de configurare al MaXSync.",
            "Configurație lipsă",
            MessageBoxButton.OK, MessageBoxImage.Information);

        var dlg = new OpenFileDialog
        {
            Title = "Selectează appsettings.json",
            Filter = "Fișiere de configurare (appsettings*.json)|appsettings*.json|Toate fișierele (*.*)|*.*",
        };
        return dlg.ShowDialog(this) == true ? dlg.FileName : null;
    }

    private void BindToUi()
    {
        TxtBaseUrl.Text = _settings.MaxPos.BaseUrl;
        TxtEmail.Text = _settings.MaxPos.Email;
        TxtPassword.Password = _settings.MaxPos.Password;
        TxtArticleInterval.Text = _settings.MaxPos.ArticleSyncIntervalMinutes.ToString(CultureInfo.InvariantCulture);
        TxtReceiptInterval.Text = _settings.MaxPos.ReceiptExportIntervalMinutes.ToString(CultureInfo.InvariantCulture);

        TxtFbHost.Text = _settings.Firebird.Host;
        TxtFbPort.Text = _settings.Firebird.Port.ToString(CultureInfo.InvariantCulture);
        TxtFbDatabase.Text = _settings.Firebird.Database;
        TxtFbUser.Text = _settings.Firebird.Username;
        TxtFbPassword.Password = _settings.Firebird.Password;
        SelectComboValue(CmbFbCharset, _settings.Firebird.Charset);

        TxtReceiptPrefix.Text = _settings.Sync.ReceiptNumberPrefix;
        TxtDefaultClientCode.Text = _settings.Sync.DefaultClientCode;
        TxtDefaultClientName.Text = _settings.Sync.DefaultClientName;
        TxtDefaultGestiuneCode.Text = _settings.Sync.DefaultGestiuneCode;
        TxtDefaultGestiuneName.Text = _settings.Sync.DefaultGestiuneName;
        ChkSyncOnlyActive.IsChecked = _settings.Sync.SyncOnlyActiveArticles;
    }

    private static void SelectComboValue(ComboBox combo, string value)
    {
        foreach (var item in combo.Items)
        {
            if (item is ComboBoxItem cbi && string.Equals(cbi.Content?.ToString(), value, StringComparison.OrdinalIgnoreCase))
            {
                combo.SelectedItem = cbi;
                return;
            }
        }
        if (combo.Items.Count > 0) combo.SelectedIndex = 0;
    }

    private void CollectFromUi()
    {
        _settings.MaxPos.BaseUrl = TxtBaseUrl.Text.Trim();
        _settings.MaxPos.Email = TxtEmail.Text.Trim();
        _settings.MaxPos.Password = TxtPassword.Password;
        _settings.MaxPos.ArticleSyncIntervalMinutes = ParseInt(TxtArticleInterval.Text, _settings.MaxPos.ArticleSyncIntervalMinutes);
        _settings.MaxPos.ReceiptExportIntervalMinutes = ParseInt(TxtReceiptInterval.Text, _settings.MaxPos.ReceiptExportIntervalMinutes);

        _settings.Firebird.Host = TxtFbHost.Text.Trim();
        _settings.Firebird.Port = ParseInt(TxtFbPort.Text, _settings.Firebird.Port);
        _settings.Firebird.Database = TxtFbDatabase.Text.Trim();
        _settings.Firebird.Username = TxtFbUser.Text.Trim();
        _settings.Firebird.Password = TxtFbPassword.Password;
        _settings.Firebird.Charset = (CmbFbCharset.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "UTF8";

        _settings.Sync.ReceiptNumberPrefix = TxtReceiptPrefix.Text.Trim();
        _settings.Sync.DefaultClientCode = TxtDefaultClientCode.Text.Trim();
        _settings.Sync.DefaultClientName = TxtDefaultClientName.Text.Trim();
        _settings.Sync.DefaultGestiuneCode = TxtDefaultGestiuneCode.Text.Trim();
        _settings.Sync.DefaultGestiuneName = TxtDefaultGestiuneName.Text.Trim();
        _settings.Sync.SyncOnlyActiveArticles = ChkSyncOnlyActive.IsChecked ?? true;
    }

    private static int ParseInt(string text, int fallback)
        => int.TryParse(text, NumberStyles.Integer, CultureInfo.InvariantCulture, out var v) ? v : fallback;

    private void UpdateConfigPathLabel()
    {
        LblConfigPath.Text = _configPath is null
            ? "Configurație: (neîncărcată)"
            : $"Configurație: {_configPath}";
    }

    private void RefreshServiceStatus()
    {
        var state = _serviceControl.GetState();
        switch (state)
        {
            case ServiceState.Running:
                ServiceStatusDot.Fill = DotGreen;
                ServiceStatusText.Text = "Serviciu: Activ ●";
                LblServiceHint.Text = string.Empty;
                BtnServiceStart.IsEnabled = false;
                BtnServiceStop.IsEnabled = true;
                BtnServiceRestart.IsEnabled = true;
                break;
            case ServiceState.Stopped:
                ServiceStatusDot.Fill = DotRed;
                ServiceStatusText.Text = "Serviciu: Oprit ●";
                LblServiceHint.Text = string.Empty;
                BtnServiceStart.IsEnabled = true;
                BtnServiceStop.IsEnabled = false;
                BtnServiceRestart.IsEnabled = true;
                break;
            case ServiceState.NotInstalled:
                ServiceStatusDot.Fill = DotGrey;
                ServiceStatusText.Text = "Serviciu: neinstalat ●";
                LblServiceHint.Text = "Serviciul MaXSync nu este instalat. Rulați install-service.ps1 ca administrator.";
                BtnServiceStart.IsEnabled = false;
                BtnServiceStop.IsEnabled = false;
                BtnServiceRestart.IsEnabled = false;
                break;
            default:
                ServiceStatusDot.Fill = DotOrange;
                ServiceStatusText.Text = "Serviciu: tranziție ●";
                LblServiceHint.Text = string.Empty;
                BtnServiceStart.IsEnabled = false;
                BtnServiceStop.IsEnabled = false;
                BtnServiceRestart.IsEnabled = false;
                break;
        }
    }

    // ----- Service control -----

    private void BtnServiceStart_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            _serviceControl.Start();
            RefreshServiceStatus();
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, ex.Message, "Eroare la pornire", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnServiceStop_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            _serviceControl.Stop();
            RefreshServiceStatus();
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, ex.Message, "Eroare la oprire", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnServiceRestart_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            _serviceControl.Restart();
            RefreshServiceStatus();
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, ex.Message, "Eroare la repornire", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    // ----- Test buttons -----

    private async void BtnTestApi_Click(object sender, RoutedEventArgs e)
    {
        BtnTestApi.IsEnabled = false;
        LblTestApiResult.Foreground = DotGrey;
        LblTestApiResult.Text = "Se testează...";

        try
        {
            using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
            var baseUrl = TxtBaseUrl.Text.Trim().TrimEnd('/') + "/";
            http.BaseAddress = new Uri(baseUrl);
            var payload = new { email = TxtEmail.Text.Trim(), password = TxtPassword.Password };

            using var resp = await http.PostAsJsonAsync("api/v1/auth/login", payload);
            if (resp.IsSuccessStatusCode)
            {
                LblTestApiResult.Foreground = DotGreen;
                LblTestApiResult.Text = $"✔ Conexiune OK ({(int)resp.StatusCode})";
            }
            else
            {
                var raw = await resp.Content.ReadAsStringAsync();
                LblTestApiResult.Foreground = DotRed;
                LblTestApiResult.Text = $"✘ {(int)resp.StatusCode} {resp.ReasonPhrase}";
                if (!string.IsNullOrWhiteSpace(raw))
                {
                    LblTestApiResult.ToolTip = raw.Length > 800 ? raw[..800] : raw;
                }
            }
        }
        catch (Exception ex)
        {
            LblTestApiResult.Foreground = DotRed;
            LblTestApiResult.Text = $"✘ {ex.Message}";
        }
        finally
        {
            BtnTestApi.IsEnabled = true;
        }
    }

    private async void BtnTestFirebird_Click(object sender, RoutedEventArgs e)
    {
        BtnTestFirebird.IsEnabled = false;
        LblTestFbResult.Foreground = DotGrey;
        LblTestFbResult.Text = "Se testează...";

        try
        {
            var b = new FbConnectionStringBuilder
            {
                DataSource = TxtFbHost.Text.Trim(),
                Port = ParseInt(TxtFbPort.Text, 3050),
                Database = TxtFbDatabase.Text.Trim(),
                UserID = TxtFbUser.Text.Trim(),
                Password = TxtFbPassword.Password,
                Charset = "UTF8",
                ServerType = FbServerType.Default,
                Pooling = false,
            };

            await Task.Run(() =>
            {
                using var conn = new FbConnection(b.ToString());
                conn.Open();
                using var cmd = new FbCommand("SELECT 1 FROM RDB$DATABASE", conn);
                cmd.ExecuteScalar();
            });

            LblTestFbResult.Foreground = DotGreen;
            LblTestFbResult.Text = "✔ Conexiune Firebird OK";
        }
        catch (Exception ex)
        {
            LblTestFbResult.Foreground = DotRed;
            LblTestFbResult.Text = $"✘ {ex.Message}";
        }
        finally
        {
            BtnTestFirebird.IsEnabled = true;
        }
    }

    // ----- Browse / save / cancel -----

    private void BtnBrowseDatabase_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new OpenFileDialog
        {
            Title = "Selectează baza Saga",
            Filter = "Firebird database (*.fdb;*.gdb)|*.fdb;*.gdb|Toate fișierele (*.*)|*.*",
        };
        if (!string.IsNullOrWhiteSpace(TxtFbDatabase.Text))
        {
            try
            {
                var dir = Path.GetDirectoryName(TxtFbDatabase.Text);
                if (!string.IsNullOrEmpty(dir) && Directory.Exists(dir))
                    dlg.InitialDirectory = dir;
            }
            catch { /* ignora cale invalida */ }
        }
        if (dlg.ShowDialog(this) == true)
        {
            TxtFbDatabase.Text = dlg.FileName;
        }
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        if (_configPath is null)
        {
            MessageBox.Show(this, "Calea fișierului de configurare nu este setată.", "Eroare",
                MessageBoxButton.OK, MessageBoxImage.Error);
            return;
        }

        CollectFromUi();
        try
        {
            _configService.Save(_configPath, _settings);
            ShowSavedToast();
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, $"Salvare eșuată:\n\n{ex.Message}", "Eroare",
                MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnCancel_Click(object sender, RoutedEventArgs e)
    {
        if (_configPath is not null)
        {
            try
            {
                _settings = _configService.Load(_configPath);
                BindToUi();
            }
            catch
            {
                // ramane ce e in UI
            }
        }
    }

    private void ShowSavedToast()
    {
        LblSavedToast.Text = "✔ Salvat cu succes!";
        LblSavedToast.Visibility = Visibility.Visible;

        var hide = new DispatcherTimer { Interval = TimeSpan.FromSeconds(3) };
        hide.Tick += (s, _) =>
        {
            LblSavedToast.Visibility = Visibility.Collapsed;
            ((DispatcherTimer)s!).Stop();
        };
        hide.Start();
    }
}
