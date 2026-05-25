// Debian autoloader for file2sharepoint
// Load dependency autoloaders
require_once '/usr/share/php/Ease/autoload.php';
require_once '/usr/share/php/Office365/SharePoint/autoload.php';

// PSR-4 autoloader for application classes
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'VitexSoftware\\File2SharePoint\\' => '/usr/lib/file2sharepoint/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
