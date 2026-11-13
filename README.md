![![Banner]](https://banners.beyondco.de/Flysystem%20OneDrive.png?theme=light&packageManager=composer+require&packageName=justus%2Fflysystem-onedrive&pattern=architect&style=style_1&description=A+flysystem+driver+for+OneDrive+that+uses+the+Microsoft+Graph+API&md=1&showWatermark=0&fontSize=100px&images=cloud)

# Flysystem adapter for Microsoft OneDrive
This package contains a Flysystem OneDrive adapter, which makes use of the Microsoft Graph API.
The adapter is ready for the latest Laravel 12.x version.

You can use this package to access files stored in onedrive or sharepoint folders from your PHP or Laravel web applications.

[![CI](https://github.com/doerffler/flysystem-onedrive/actions/workflows/code_checks.yaml/badge.svg?branch=main)](https://github.com/doerffler/flysystem-onedrive/actions/workflows/code_checks.yaml) 
[![Latest Stable Version](http://poser.pugx.org/justus/flysystem-onedrive/v)](https://packagist.org/packages/justus/flysystem-onedrive)
[![Total Downloads](http://poser.pugx.org/justus/flysystem-onedrive/downloads)](https://packagist.org/packages/justus/flysystem-onedrive) 
[![Latest Unstable Version](http://poser.pugx.org/justus/flysystem-onedrive/v/unstable)](https://packagist.org/packages/justus/flysystem-onedrive)
[![License](http://poser.pugx.org/justus/flysystem-onedrive/license)](https://packagist.org/packages/justus/flysystem-onedrive) 
[![PHP Version Require](http://poser.pugx.org/justus/flysystem-onedrive/require/php)](https://packagist.org/packages/justus/flysystem-onedrive)

## 1. Installation
Simply install the package using composer:

```bash
composer require justus/flysystem-onedrive
```

## 2. Usage

### Laravel Usage
1. Add the following variables to your ``.env`` file

example for 'personal' OneDrive
```dotenv
ONEDRIVE_ROOT=me
ONEDRIVE_ACCESS_TOKEN=fd6s7a98...
ONEDRIVE_DIR_TYPE=drives
```
example for 'group shared' OneDrive
```dotenv
ONEDRIVE_ROOT="{group_id}/drive"
ONEDRIVE_ACCESS_TOKEN=fd6s7a98...
ONEDRIVE_DIR_TYPE=groups
```


2. In the file ``config/filesystems.php``, please add the following code snippet to the disks section

```php
'onedrive' => [
    'driver' => 'onedrive',
    'root' => env('ONEDRIVE_ROOT'),
    'access_token' => env('ONEDRIVE_ACCESS_TOKEN'), //optional if demanded
    'directory_type' => env('ONEDRIVE_DIR_TYPE')
],
```

3. Add the ``OneDriveAdapterServiceProvider`` in ``config/app.php``

```php
'providers' => [
    // ...
    Justus\FlysystemOneDrive\Providers\OneDriveAdapterServiceProvider::class,
    // ...
],
```

There are two established approaches of using the package
- On demand: Recommended if you use a dynamic graph access token. (usage e. g. session('graph_access_token'))
```php
$disk = Storage::build([
    'driver' => config('filesystems.disks.onedrive.driver'),
    'root' => config('filesystems.disks.onedrive.root'),
    'directory_type' => config('filesystems.disks.onedrive.directory_type'),
    'access_token' => session('graph_access_token')
]);

$disk->makeDirectory('test');
```
- Default with Storage Facade: Recommended if you use a static graph access token.
```php
Storage::disk('onedrive')->makeDirectory('test');
```
### Symfony Usage
Register the bundle and configure it, e.g. in ``config/packages/flysystem_one_drive_adapter.yaml``:

```yaml
flysystem_one_drive_adapter:
  credentials:
    tenant_id: '%env(MICROSOFT_TENANT_ID)%'
    client_id: '%env(MICROSOFT_CLIENT_ID)%'
    client_secret: '%env(MICROSOFT_CLIENT_SECRET)%'
  http:
    # Seconds to wait for DNS, TCP connect and TLS handshake (default 1.0, 0 disables it).
    # Running transfers (chunked uploads, downloads, long Graph calls) are not limited by it.
    connect_timeout: 1.0
    # Total seconds a single Graph API request may take (default 100, the Graph SDK default, 0 waits indefinitely).
    request_timeout: 100
  drives:
    IndustrieUmgebung:
      # Display name of the SharePoint site, used by the legacy site search when "site" is not configured.
      drive: 'IndustrieUmgebung'
      # Optional, strongly recommended: resolves the site by its path (GET /sites/{hostname}:/{path})
      # instead of the slow and unreliable search endpoint (GET /sites?search=...).
      # "path" may be decoded or percent-encoded. "site: ~" falls back to the search.
      site:
        hostname: 'contoso.sharepoint.com'
        path: 'sites/IndustrieUmgebung'
      # Passed to the OneDriveAdapter. "directory_type" is always "sites".
      options:
        chunk_size: 3276800 # must be a multiple of 327680 (320 KiB)
```

Every drive is available as the public service ``perspeqtive_flysystem.onedriveadapter.<name>``.

The connect timeout applies to both transports used by the bundle:
- Graph API requests (Guzzle) use the Guzzle ``connect_timeout`` request option.
- Token requests, chunk uploads and downloads (Symfony HttpClient) use the
  ``perspeqtive.flysystem_one_drive.http_client`` service, a decorator of
  ``Symfony\Contracts\HttpClient\HttpClientInterface`` that aborts a request with a
  ``TransportExceptionInterface`` when no connection could be established in time. The regular
  idle ``timeout`` of the http client is not changed.

Services intended for reuse:

| Service id | Interface |
| --- | --- |
| ``perspeqtive.flysystem_one_drive.graph.graph_provider`` | ``PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProviderInterface`` |
| ``perspeqtive.flysystem_one_drive.site.site_id_resolver`` (alias ``PERSPEQTIVE\FlysystemOneDrive\Site\SiteIdResolverInterface``) | ``SiteIdResolverInterface::resolve(string $driveIdentifier): string`` resolves the site id of a configured drive and throws ``SiteNotFoundException`` / ``UnknownDriveException`` |
| ``perspeqtive.flysystem_one_drive.http_client`` | ``Symfony\Contracts\HttpClient\HttpClientInterface`` with connect timeout |

### PHP Usage
Usage in php without Laravel framework
```php
$options = [

];

$graph = new Graph();
$graph->setAccessToken('fd6s7a98...');

$adapter = new OneDriveAdapter($graph, 'root/path', $options);

$filesystem = new Filesystem($adapter);

$filesystem->createDirectory('test');
```

## 3. Changelog
Please see CHANGELOG for more information on recent changes.

## 4. Testing
```bash
composer test
```

## 5. Security
If you discover any security related issues, please write an email to jdonner@doerffler.com instead of using the issue tracker.

## 6. License
The MIT License (MIT). Please see License File for more information.
