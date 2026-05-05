namespace MaXSync.Services;

public sealed class FirebirdOptions
{
    public string Host { get; set; } = "localhost";
    public int Port { get; set; } = 3060;
    public string Database { get; set; } = string.Empty;
    public string Username { get; set; } = "SYSDBA";
    public string Password { get; set; } = "mastersaga";
    public string Charset { get; set; } = "WIN1252";

    public string BuildConnectionString()
    {
        var b = new FirebirdSql.Data.FirebirdClient.FbConnectionStringBuilder
        {
            DataSource = Host,
            Port = Port,
            Database = Database,
            UserID = Username,
            Password = Password,
            Charset = Charset,
            ServerType = FirebirdSql.Data.FirebirdClient.FbServerType.Default,
            Pooling = true,
        };
        return b.ToString();
    }
}
