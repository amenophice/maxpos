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

    private static readonly SolidColorBrush DotGreen = new(Color.FromRgb(0x4C, 0xAF, 0x50));
    private static readonly SolidColorBrush DotRed = new(Color.FromRgb(0xB5, 0x44, 0x3A));
    private static readonly SolidColorBrush DotOrange = new(Color.FromRgb(0xFF, 0x98, 0x00));
    private static readonly SolidColorBrush DotGrey = new(Color.FromRgb(0x9E, 0x9E, 0x9E));

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

        TxtReceiptPrefix.Text = _settings.MaxPos.ReceiptNumberPrefix;
        TxtDefaultClientCode.Text = _settings.MaxPos.DefaultClientCode;
        TxtDefaultClientName.Text = _settings.MaxPos.DefaultClientName;
        TxtDefaultGestiuneCode.Text = _settings.MaxPos.DefaultGestiuneCode;
        TxtDefaultGestiuneName.Text = _settings.MaxPos.DefaultGestiuneName;
        ChkSyncOnlyActive.IsChecked = _settings.MaxPos.SyncOnlyActiveArticles;
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

        _settings.MaxPos.ReceiptNumberPrefix = TxtReceiptPrefix.Text.Trim();
        _settings.MaxPos.DefaultClientCode = TxtDefaultClientCode.Text.Trim();
        _settings.MaxPos.DefaultClientName = TxtDefaultClientName.Text.Trim();
        _settings.MaxPos.DefaultGestiuneCode = TxtDefaultGestiuneCode.Text.Trim();
        _settings.MaxPos.DefaultGestiuneName = TxtDefaultGestiuneName.Text.Trim();
        _settings.MaxPos.SyncOnlyActiveArticles = ChkSyncOnlyActive.IsChecked ?? true;
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
                BtnServiceInstall.Visibility = Visibility.Collapsed;
                BtnServiceStart.Visibility = Visibility.Collapsed;
                BtnServiceStop.Visibility = Visibility.Visible;
                BtnServiceRestart.Visibility = Visibility.Visible;
                BtnServiceUninstall.Visibility = Visibility.Collapsed;
                BtnServiceStop.IsEnabled = true;
                BtnServiceRestart.IsEnabled = true;
                break;
            case ServiceState.Stopped:
                ServiceStatusDot.Fill = DotOrange;
                ServiceStatusText.Text = "Serviciu: Oprit ●";
                LblServiceHint.Text = string.Empty;
                BtnServiceInstall.Visibility = Visibility.Collapsed;
                BtnServiceStart.Visibility = Visibility.Visible;
                BtnServiceStop.Visibility = Visibility.Collapsed;
                BtnServiceRestart.Visibility = Visibility.Collapsed;
                BtnServiceUninstall.Visibility = Visibility.Visible;
                BtnServiceStart.IsEnabled = true;
                BtnServiceUninstall.IsEnabled = true;
                break;
            case ServiceState.NotInstalled:
                ServiceStatusDot.Fill = DotGrey;
                ServiceStatusText.Text = "Serviciu: neinstalat ●";
                LblServiceHint.Text = "Serviciul MaXSync nu este instalat. Rulați install-service.ps1 ca administrator.";
                BtnServiceInstall.Visibility = Visibility.Visible;
                BtnServiceStart.Visibility = Visibility.Collapsed;
                BtnServiceStop.Visibility = Visibility.Collapsed;
                BtnServiceRestart.Visibility = Visibility.Collapsed;
                BtnServiceUninstall.Visibility = Visibility.Collapsed;
                BtnServiceInstall.IsEnabled = true;
                break;
            default:
                ServiceStatusDot.Fill = DotRed;
                ServiceStatusText.Text = "Serviciu: tranziție ●";
                LblServiceHint.Text = string.Empty;
                BtnServiceInstall.Visibility = Visibility.Collapsed;
                BtnServiceStart.Visibility = Visibility.Collapsed;
                BtnServiceStop.Visibility = Visibility.Collapsed;
                BtnServiceRestart.Visibility = Visibility.Collapsed;
                BtnServiceUninstall.Visibility = Visibility.Collapsed;
                break;
        }
    }

    private string? FindMaXSyncExe()
    {
        var exeDir = AppContext.BaseDirectory;
        var candidates = new List<string>
        {
            Path.GetFullPath(Path.Combine(exeDir, "..", "MaXSync", "MaXSync.exe")),
            Path.Combine(exeDir, "MaXSync.exe"),
        };

        if (_configPath is not null)
        {
            var configDir = Path.GetDirectoryName(_configPath);
            if (!string.IsNullOrEmpty(configDir))
                candidates.Add(Path.Combine(configDir, "MaXSync.exe"));
        }

        foreach (var c in candidates)
        {
            if (File.Exists(c)) return c;
        }
        return null;
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
        if (SaveConfig()) ShowSavedToast();
    }

    private bool SaveConfig()
    {
        if (_configPath is null)
        {
            MessageBox.Show(this, "Calea fișierului de configurare nu este setată.", "Eroare",
                MessageBoxButton.OK, MessageBoxImage.Error);
            return false;
        }

        CollectFromUi();
        try
        {
            _configService.Save(_configPath, _settings);
            return true;
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, $"Salvare eșuată:\n\n{ex.Message}", "Eroare",
                MessageBoxButton.OK, MessageBoxImage.Error);
            return false;
        }
    }

    // ----- Install / Uninstall -----

    private void BtnServiceInstall_Click(object sender, RoutedEventArgs e)
    {
        var exePath = FindMaXSyncExe();
        if (exePath is null)
        {
            MessageBox.Show(this,
                "Nu am găsit MaXSync.exe. Asigurați-vă că MaXSyncConfig.exe și MaXSync.exe sunt în același folder părinte.",
                "MaXSync.exe lipsă",
                MessageBoxButton.OK, MessageBoxImage.Warning);
            return;
        }

        if (!SaveConfig()) return;

        BtnServiceInstall.IsEnabled = false;
        try
        {
            _serviceControl.InstallService(
                exePath,
                "MaXPos Saga Sync Agent",
                "Sincronizeaza date intre Saga Firebird si MaXPos POS");

            // Lasa SCM sa proceseze inregistrarea inainte sa il interogam.
            Thread.Sleep(2000);
            RefreshServiceStatus();

            if (_serviceControl.GetState() == ServiceState.NotInstalled)
            {
                MessageBox.Show(this,
                    "Serviciul a fost creat, dar nu apare încă în SCM. Reîncercați după câteva secunde.",
                    "Atenție", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            MessageBox.Show(this,
                "Serviciul MaXSync a fost instalat cu succes!",
                "Instalare reușită", MessageBoxButton.OK, MessageBoxImage.Information);

            try
            {
                _serviceControl.Start();
            }
            catch (Exception startEx)
            {
                MessageBox.Show(this,
                    $"Serviciul a fost instalat, dar nu a putut fi pornit:\n\n{startEx.Message}",
                    "Atenție", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
            RefreshServiceStatus();
        }
        catch (ServiceControlService.ElevationCancelledException)
        {
            MessageBox.Show(this,
                "Instalarea necesită drepturi de Administrator.",
                "Operațiune anulată", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
        catch (ServiceControlService.ScCommandException ex)
        {
            MessageBox.Show(this,
                $"Eroare la instalare. Verificați că MaXSync.exe există.\n\nDetalii: {ex.Message}",
                "Eroare la instalare", MessageBoxButton.OK, MessageBoxImage.Error);
        }
        catch (Exception ex)
        {
            MessageBox.Show(this,
                $"Eroare la instalare:\n\n{ex.Message}",
                "Eroare", MessageBoxButton.OK, MessageBoxImage.Error);
        }
        finally
        {
            BtnServiceInstall.IsEnabled = true;
        }
    }

    private void BtnServiceUninstall_Click(object sender, RoutedEventArgs e)
    {
        var confirm = MessageBox.Show(this,
            "Sigur doriți să dezinstalați serviciul MaXSync?",
            "Confirmare dezinstalare",
            MessageBoxButton.YesNo, MessageBoxImage.Question, MessageBoxResult.No);
        if (confirm != MessageBoxResult.Yes) return;

        BtnServiceUninstall.IsEnabled = false;
        try
        {
            try { _serviceControl.Stop(TimeSpan.FromSeconds(15)); }
            catch { /* poate sa fie deja oprit */ }

            _serviceControl.UninstallService();

            Thread.Sleep(1500);
            RefreshServiceStatus();

            MessageBox.Show(this,
                "Serviciul MaXSync a fost dezinstalat.",
                "Dezinstalare reușită", MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (ServiceControlService.ElevationCancelledException)
        {
            MessageBox.Show(this,
                "Dezinstalarea necesită drepturi de Administrator.",
                "Operațiune anulată", MessageBoxButton.OK, MessageBoxImage.Warning);
        }
        catch (Exception ex)
        {
            MessageBox.Show(this,
                $"Eroare la dezinstalare:\n\n{ex.Message}",
                "Eroare", MessageBoxButton.OK, MessageBoxImage.Error);
        }
        finally
        {
            BtnServiceUninstall.IsEnabled = true;
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
