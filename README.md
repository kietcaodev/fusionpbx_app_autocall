# Autocall Module for FusionPBX

Autocall API with ERP integration for automatic call campaigns. This module provides a REST API to initiate calls via FreeSWITCH with automatic extension routing from ERP systems.

## Features

- **REST API for Autocall**: Initiate calls programmatically via JSON API
- **ERP Integration**: Automatically get available extension from ERP when destination is not specified
- **Multi-Company Support**: Configure multiple ERP endpoints with different bearer tokens
- **Web UI Management**: Manage configurations through FusionPBX web interface
- **Security**: IP whitelist and HTTP Basic Authentication
- **Logging**: Comprehensive logging for audit and debugging

## Installation

Tested with FusionPBX 4.5.1+ and FreeSWITCH 1.10+

### 1. Copy module to FusionPBX

```bash
cd /var/www/fusionpbx/app
cp -R /path/to/autocall .
chown -R www-data:www-data /var/www/fusionpbx/app/autocall
```

### 2. Run FusionPBX Upgrade

Navigate to your FusionPBX web interface:
- Go to **Advanced** → **Upgrade**
- Run upgrades for:
  - **App Defaults** (creates database table)
  - **Menu Defaults** (adds menu item)
  - **Permission Defaults** (sets up permissions)

### 3. Log out and back in

The **Autocall** menu item will appear under **Status** or **Apps** section.

## Configuration

### 1. Add ERP Configuration

Go to **Status** → **Autocall** in FusionPBX web interface.

Click **Add** and configure:

| Field | Description | Example |
|-------|-------------|---------|
| **Company Name** | Identifier for ERP system | `erp.zozin.vn` |
| **Company URL** | Full URL to ERP | `https://erp.zozin.vn` |
| **Bearer Token** | JWT token for ERP API authentication | `12312312312...` |
| **ESL Host** | FreeSWITCH ESL host | `127.0.0.1` |
| **ESL Port** | FreeSWITCH ESL port | `8021` |
| **ESL Password** | FreeSWITCH ESL password | `ClueCon` |
| **Domain** | FreeSWITCH domain | `tongdai.zozin.vn` |
| **Enabled** | Enable/disable this config | `True` |
| **Description** | Optional notes | `Production ERP` |

### 2. Configure IP Whitelist and Users

Edit `config.php`:

```php
<?php
// Allowed IPs
$allowedIps = [
    '192.168.1.100',
    '10.0.0.50'
];

// Valid users for Basic Auth
$validUsers = [
    'clicktocall' => '12312312312'
];
?>
```

## API Usage

### Endpoint

```
POST https://tongdai.zozin.vn/app/autocall/autocall.php
```

### Authentication

HTTP Basic Authentication:
```
Username: clicktocall
Password: 12312312312
```

### Request Body

#### Option 1: With destination (direct routing)

```json
{
    "campaign_id": "123456789",
    "callee": "90399726129",
    "destination": "2222",
    "timeout": "60"
}
```

#### Option 2: Without destination (ERP lookup)

```json
{
    "campaign_id": "123456789",
    "callee": "90399726129",
    "destination": "",
    "customer_id": "123456",
    "company_name": "erp.zozin.vn",
    "timeout": "60"
}
```

### Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `campaign_id` | Yes | Campaign identifier (shows as caller ID) |
| `callee` | Yes | Phone number to call |
| `destination` | No | Target extension/queue. If empty, fetched from ERP |
| `customer_id` | Conditional | Required when `destination` is empty |
| `company_name` | Conditional | Required when `destination` is empty |
| `timeout` | Yes | Call timeout in seconds |

### Response Codes

| Code | Description |
|------|-------------|
| `200` | Call initiated successfully |
| `400` | Missing required parameters |
| `401` | Unauthorized (invalid credentials) |
| `403` | Forbidden (IP not whitelisted) |
| `486` | Failed to initiate call (FreeSWITCH error) |
| `500` | Server error (ESL connection failed or ERP API error) |

### Success Response

```json
{
    "status": "200",
    "message": "Call initiated successfully.",
    "uuid": "f81d4fae-7dec-11d0-a765-00a0c91e6bf6",
    "destination": "1300"
}
```

### Error Response

```json
{
    "status": "400",
    "message": "Required parameters are missing: campaign_id, callee, and timeout are mandatory."
}
```

## ERP API Integration

When `destination` is empty, the module calls your ERP API to get an available extension.

### ERP API Endpoint

```
POST https://{company_url}/api/crm/campaign/get-available-extension
```

### Request Headers

```
Authorization: Bearer {bearer_token}
X-Requested-With: XMLHttpRequest
```

### Request Body (form-data)

```
customer_id: {customer_id}
```

### Expected ERP Response

```json
{
    "success": true,
    "extension": "1300"
}
```

## Example cURL Requests

### With destination:

```bash
curl -X POST https://tongdai.zozin.vn/app/autocall/autocall.php \
  -u "clicktocall:12312312312" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "123456789",
    "callee": "90399726129",
    "destination": "2222",
    "timeout": "60"
  }'
```

### Without destination (ERP lookup):

```bash
curl -X POST https://tongdai.zozin.vn/app/autocall/autocall.php \
  -u "clicktocall:12312312312" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "123456789",
    "callee": "90399726129",
    "destination": "",
    "customer_id": "123456",
    "company_name": "erp.zozin.vn",
    "timeout": "60"
  }'
```

## Logging

Logs are written to:
```
/var/log/freeswitch/autocall.log
```

View logs:
```bash
tail -f /var/log/freeswitch/autocall.log
```

## Troubleshooting

### Issue: "Failed to connect to ESL"

**Solution**: Check FreeSWITCH is running and ESL settings are correct:
```bash
fs_cli -x "status"
```

### Issue: "Failed to get available extension"

**Solution**: 
1. Check bearer token is correct in Autocall settings
2. Verify company_name matches configured setting
3. Check ERP API is accessible
4. Review logs for detailed error

### Issue: "Forbidden: Your IP is not allowed"

**Solution**: Add your IP to `$allowedIps` array in `config.php`

### Issue: "Unauthorized access"

**Solution**: Verify HTTP Basic Auth credentials match `$validUsers` in `config.php`

## Security Notes

1. **Use HTTPS** in production
2. **Rotate bearer tokens** regularly
3. **Limit IP whitelist** to trusted sources only
4. **Monitor logs** for suspicious activity
5. **Use strong passwords** for Basic Auth

## Support

For issues or questions, contact your system administrator.

## License

Mozilla Public License 1.1 (MPL 1.1)

