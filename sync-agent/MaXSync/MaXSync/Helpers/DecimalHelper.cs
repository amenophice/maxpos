using System.Globalization;

namespace MaXSync.Helpers;

// Conversii sigure pentru valorile NUMERIC din Firebird.
public static class DecimalHelper
{
    public static decimal ToDecimal(object? value, decimal fallback = 0m)
    {
        if (value is null || value is DBNull) return fallback;
        return Convert.ToDecimal(value, CultureInfo.InvariantCulture);
    }

    public static decimal? ToNullableDecimal(object? value)
    {
        if (value is null || value is DBNull) return null;
        return Convert.ToDecimal(value, CultureInfo.InvariantCulture);
    }

    public static long? ToNullableLong(object? value)
    {
        if (value is null || value is DBNull) return null;
        return Convert.ToInt64(value, CultureInfo.InvariantCulture);
    }

    public static short ToShort(object? value, short fallback = 0)
    {
        if (value is null || value is DBNull) return fallback;
        return Convert.ToInt16(value, CultureInfo.InvariantCulture);
    }

    public static string ToTrimmedString(object? value)
    {
        if (value is null || value is DBNull) return string.Empty;
        return Convert.ToString(value, CultureInfo.InvariantCulture)?.Trim() ?? string.Empty;
    }

    public static string? ToTrimmedNullableString(object? value)
    {
        if (value is null || value is DBNull) return null;
        var s = Convert.ToString(value, CultureInfo.InvariantCulture)?.Trim();
        return string.IsNullOrEmpty(s) ? null : s;
    }
}
