namespace MaXSync.Services;

public sealed class FirebirdOptions
{
    public string Host { get; set; } = "localhost";
    public int Port { get; set; } = 3050;
    public string Database { get; set; } = string.Empty;
    public string Username { get; set; } = "SYSDBA";
    public string Password { get; set; } = "masterkey";
    // Pastrat in config doar pentru retrocompatibilitate; ignorat la construirea
    // sirului de conexiune. Folosim mereu UTF8 — FirebirdClient face conversia
    // automata din charset-ul real al coloanelor (WIN1252 in Saga).
    public string Charset { get; set; } = "UTF8";

    public string BuildConnectionString()
    {
        var b = new FirebirdSql.Data.FirebirdClient.FbConnectionStringBuilder
        {
            DataSource = Host,
            Port = Port,
            Database = Database,
            UserID = Username,
            Password = Password,
            Charset = "UTF8",
            ServerType = FirebirdSql.Data.FirebirdClient.FbServerType.Default,
            Pooling = true,
        };
        return b.ToString();
    }
}
