#!/usr/bin/env php
<?php
/**
 * Project Structure Checker for PHP Projects
 * Run this script from your project root to analyze the current structure
 */

class ProjectStructureChecker {
    private $rootPath;
    private $expectedDirs = [
        'controllers' => 'Controller files (handles business logic)',
        'helpers' => 'Helper/utility functions',
        'apis' => 'API endpoints and routing',
        'models' => 'Data models and database interactions',
        'public' => 'Public UI files (CSS, JS, HTML, PHP views)'
    ];
    
    private $optionalDirs = [
        'config' => 'Configuration files',
        'middlewares' => 'Middleware functions',
        'routes' => 'Route definitions',
        'services' => 'Service layer for complex logic',
        'tests' => 'Unit and integration tests',
        'views' => 'View templates',
        'database' => 'Database migrations/seeds',
        'logs' => 'Log files directory',
        'uploads' => 'User uploaded files',
        'storage' => 'Cache and temporary files',
        'vendor' => 'Composer dependencies'
    ];
    
    private $results = [];
    
    public function __construct($rootPath = '.') {
        $this->rootPath = realpath($rootPath);
        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'root_path' => $this->rootPath,
            'present_dirs' => [],
            'missing_dirs' => [],
            'files_by_type' => [],
            'warnings' => [],
            'recommendations' => []
        ];
    }
    
    public function checkDirectoryStructure() {
        echo "\n📁 Analyzing project structure at: {$this->rootPath}\n\n";
        
        // Check required directories
        echo "🔍 Checking required directories...\n";
        foreach ($this->expectedDirs as $dirName => $description) {
            $dirPath = $this->rootPath . DIRECTORY_SEPARATOR . $dirName;
            if (is_dir($dirPath)) {
                $this->results['present_dirs'][] = $dirName;
                echo "  ✅ {$dirName}/ - {$description}\n";
            } else {
                $this->results['missing_dirs'][] = $dirName;
                echo "  ❌ {$dirName}/ - {$description} (MISSING)\n";
            }
        }
        
        // Check optional directories
        echo "\n📦 Checking optional directories...\n";
        foreach ($this->optionalDirs as $dirName => $description) {
            $dirPath = $this->rootPath . DIRECTORY_SEPARATOR . $dirName;
            if (is_dir($dirPath)) {
                $this->results['present_dirs'][] = $dirName;
                echo "  ✓ {$dirName}/ - {$description}\n";
            }
        }
    }
    
    public function analyzeFileTypes() {
        echo "\n📄 Analyzing file types...\n";
        
        $extensions = [];
        $totalFiles = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Skip hidden files and vendor
                if (strpos($file->getFilename(), '.') === 0 || 
                    strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
                    continue;
                }
                
                $ext = $file->getExtension();
                if ($ext) {
                    $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
                } else {
                    $extensions['no_extension'] = ($extensions['no_extension'] ?? 0) + 1;
                }
                $totalFiles++;
            }
        }
        
        $this->results['files_by_type'] = $extensions;
        $this->results['total_files'] = $totalFiles;
        
        echo "  Total files: {$totalFiles}\n";
        arsort($extensions);
        $count = 0;
        foreach ($extensions as $ext => $count_files) {
            if ($count++ >= 10) break;
            echo "    .{$ext}: {$count_files} files\n";
        }
    }
    
    public function checkKeyFiles() {
        echo "\n⚙️ Checking key configuration files...\n";
        
        $keyFiles = [
            'composer.json', 'composer.lock', '.env', '.env.example', 
            'README.md', '.gitignore', 'php.ini', 'phpunit.xml',
            'docker-compose.yml', 'Dockerfile', 'Makefile'
        ];
        
        foreach ($keyFiles as $file) {
            $filePath = $this->rootPath . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                echo "  ✓ {$file}\n";
            }
        }
    }
    
    public function analyzeControllers() {
        echo "\n🎮 Analyzing controllers...\n";
        
        $controllersPath = $this->rootPath . DIRECTORY_SEPARATOR . 'controllers';
        if (is_dir($controllersPath)) {
            $phpFiles = glob($controllersPath . DIRECTORY_SEPARATOR . '*.php');
            echo "  Found " . count($phpFiles) . " controller files\n";
            
            foreach (array_slice($phpFiles, 0, 5) as $file) {
                $filename = basename($file);
                echo "    - {$filename}\n";
                
                // Quick check for class definition
                $content = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                    echo "      Class: {$matches[1]}\n";
                }
            }
        } else {
            $this->results['warnings'][] = "Controllers directory not found";
        }
    }
    
    public function analyzeModels() {
        echo "\n🗄️ Analyzing models...\n";
        
        $modelsPath = $this->rootPath . DIRECTORY_SEPARATOR . 'models';
        if (is_dir($modelsPath)) {
            $phpFiles = glob($modelsPath . DIRECTORY_SEPARATOR . '*.php');
            echo "  Found " . count($phpFiles) . " model files\n";
            
            foreach (array_slice($phpFiles, 0, 5) as $file) {
                $filename = basename($file);
                echo "    - {$filename}\n";
            }
        } else {
            $this->results['warnings'][] = "Models directory not found";
        }
    }
    
    public function analyzeHelpers() {
        echo "\n🔧 Analyzing helpers...\n";
        
        $helpersPath = $this->rootPath . DIRECTORY_SEPARATOR . 'helpers';
        if (is_dir($helpersPath)) {
            $phpFiles = glob($helpersPath . DIRECTORY_SEPARATOR . '*.php');
            echo "  Found " . count($phpFiles) . " helper files\n";
            
            foreach (array_slice($phpFiles, 0, 5) as $file) {
                $filename = basename($file);
                echo "    - {$filename}\n";
            }
        }
    }
    
    public function analyzeAPIs() {
        echo "\n🌐 Analyzing API structure...\n";
        
        $apisPath = $this->rootPath . DIRECTORY_SEPARATOR . 'apis';
        if (is_dir($apisPath)) {
            $phpFiles = glob($apisPath . DIRECTORY_SEPARATOR . '*.php');
            $jsonFiles = glob($apisPath . DIRECTORY_SEPARATOR . '*.json');
            echo "  Found " . count($phpFiles) . " PHP API files\n";
            
            // Check for REST patterns
            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);
                if (preg_match('/\$_SERVER\[\'REQUEST_METHOD\'\]/', $content)) {
                    echo "    ✓ REST method handling detected in " . basename($file) . "\n";
                }
                if (preg_match('/header\([\'"]Content-Type:\s*application\/json[\'"]/', $content)) {
                    echo "    ✓ JSON response detected in " . basename($file) . "\n";
                }
            }
        }
    }
    
    public function analyzePublicUI() {
        echo "\n🎨 Analyzing public UI structure...\n";
        
        $publicPath = $this->rootPath . DIRECTORY_SEPARATOR . 'public';
        if (is_dir($publicPath)) {
            // Check for subdirectories
            $subdirs = ['css', 'js', 'images', 'fonts', 'assets', 'uploads'];
            $foundSubdirs = [];
            
            foreach ($subdirs as $subdir) {
                if (is_dir($publicPath . DIRECTORY_SEPARATOR . $subdir)) {
                    $foundSubdirs[] = $subdir;
                }
            }
            
            if (!empty($foundSubdirs)) {
                echo "  Found subdirectories: " . implode(', ', $foundSubdirs) . "\n";
            }
            
            // Check for HTML/PHP files
            $htmlFiles = glob($publicPath . DIRECTORY_SEPARATOR . '*.html');
            $phpFiles = glob($publicPath . DIRECTORY_SEPARATOR . '*.php');
            $cssFiles = glob($publicPath . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . '*.css');
            $jsFiles = glob($publicPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . '*.js');
            
            echo "  HTML files: " . count($htmlFiles) . "\n";
            echo "  PHP files: " . count($phpFiles) . "\n";
            echo "  CSS files: " . count($cssFiles) . "\n";
            echo "  JS files: " . count($jsFiles) . "\n";
            
            // Check for main index file
            if (file_exists($publicPath . DIRECTORY_SEPARATOR . 'index.php') || 
                file_exists($publicPath . DIRECTORY_SEPARATOR . 'index.html')) {
                echo "  ✓ Main index file found\n";
            }
        } else {
            $this->results['warnings'][] = "Public directory missing";
        }
    }
    
    public function checkComposer() {
        echo "\n📦 Checking Composer configuration...\n";
        
        $composerPath = $this->rootPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composerPath)) {
            $composerContent = file_get_contents($composerPath);
            $composerData = json_decode($composerContent, true);
            
            if ($composerData) {
                if (isset($composerData['autoload'])) {
                    echo "  ✓ Autoload configured\n";
                    if (isset($composerData['autoload']['psr-4'])) {
                        echo "    PSR-4 namespaces:\n";
                        foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
                            echo "      {$namespace} => {$path}\n";
                        }
                    }
                }
                
                if (isset($composerData['require'])) {
                    echo "  Dependencies: " . count($composerData['require']) . " packages\n";
                }
                
                if (isset($composerData['require-dev'])) {
                    echo "  Dev dependencies: " . count($composerData['require-dev']) . " packages\n";
                }
            }
        } else {
            echo "  ⚠️ composer.json not found (recommended for PHP projects)\n";
            $this->results['recommendations'][] = "Initialize Composer for dependency management";
        }
    }
    
    public function checkDatabaseConfig() {
        echo "\n💾 Checking database configuration...\n";
        
        $configPath = $this->rootPath . DIRECTORY_SEPARATOR . 'config';
        $dbConfigFound = false;
        
        if (is_dir($configPath)) {
            $configFiles = glob($configPath . DIRECTORY_SEPARATOR . '*.php');
            foreach ($configFiles as $file) {
                $content = file_get_contents($file);
                if (preg_match('/(mysql|pgsql|sqlite|database)/i', $content)) {
                    echo "  ✓ Database config found in: " . basename($file) . "\n";
                    $dbConfigFound = true;
                }
            }
        }
        
        // Check .env for database variables
        $envPath = $this->rootPath . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/DB_(HOST|NAME|USER|PASSWORD)/', $envContent)) {
                echo "  ✓ Database environment variables found in .env\n";
                $dbConfigFound = true;
            }
        }
        
        if (!$dbConfigFound) {
            $this->results['warnings'][] = "No database configuration detected";
        }
    }
    
    public function suggestImprovements() {
        echo "\n💡 Recommendations:\n";
        
        if (!empty($this->results['missing_dirs'])) {
            echo "\n  Consider adding: " . implode(', ', $this->results['missing_dirs']) . "\n";
        }
        
        // Check for routing
        $routesPath = $this->rootPath . DIRECTORY_SEPARATOR . 'routes';
        if (!is_dir($routesPath) && !file_exists($this->rootPath . DIRECTORY_SEPARATOR . 'routes.php')) {
            echo "  Consider implementing a routing system (routes/ directory or routes.php)\n";
        }
        
        // Check for configuration
        $configPath = $this->rootPath . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configPath) && !file_exists($this->rootPath . DIRECTORY_SEPARATOR . 'config.php')) {
            echo "  Consider centralizing configuration in config/ directory\n";
        }
        
        // Environment file
        if (!file_exists($this->rootPath . DIRECTORY_SEPARATOR . '.env')) {
            echo "  Create .env file for environment-specific configuration\n";
        }
        if (!file_exists($this->rootPath . DIRECTORY_SEPARATOR . '.env.example')) {
            echo "  Create .env.example as a template for other developers\n";
        }
        
        // Documentation
        if (!file_exists($this->rootPath . DIRECTORY_SEPARATOR . 'README.md')) {
            echo "  Add README.md to document your project structure and setup\n";
        }
        
        // Testing
        if (!is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'tests')) {
            echo "  Consider adding tests/ directory for unit and integration tests\n";
        }
        
        // Error handling
        if (!file_exists($this->rootPath . DIRECTORY_SEPARATOR . 'error_log') &&
            !is_dir($this->rootPath . DIRECTORY_SEPARATOR . 'logs')) {
            echo "  Implement logging system (logs/ directory)\n";
        }
    }
    
    public function generateReport() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 PROJECT STRUCTURE SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        
        echo "\n✅ Present directories: " . count($this->results['present_dirs']) . "\n";
        if (!empty($this->results['present_dirs'])) {
            echo "   " . implode(', ', $this->results['present_dirs']) . "\n";
        }
        
        if (!empty($this->results['missing_dirs'])) {
            echo "\n⚠️  Missing required directories: " . count($this->results['missing_dirs']) . "\n";
            echo "   " . implode(', ', $this->results['missing_dirs']) . "\n";
        }
        
        echo "\n📁 Total files analyzed: " . ($this->results['total_files'] ?? 0) . "\n";
        
        if (!empty($this->results['warnings'])) {
            echo "\n⚠️  Warnings:\n";
            foreach ($this->results['warnings'] as $warning) {
                echo "   • {$warning}\n";
            }
        }
        
        // Save report to file
        $reportPath = $this->rootPath . DIRECTORY_SEPARATOR . 'structure_report.txt';
        $reportContent = "Project Structure Report - {$this->results['timestamp']}\n";
        $reportContent .= "Root Path: {$this->results['root_path']}\n";
        $reportContent .= "Present Directories: " . implode(', ', $this->results['present_dirs']) . "\n";
        if (!empty($this->results['missing_dirs'])) {
            $reportContent .= "Missing Directories: " . implode(', ', $this->results['missing_dirs']) . "\n";
        }
        $reportContent .= "Total Files: {$this->results['total_files']}\n\n";
        $reportContent .= "File Types:\n";
        foreach ($this->results['files_by_type'] as $ext => $count) {
            $reportContent .= "  .{$ext}: {$count}\n";
        }
        
        file_put_contents($reportPath, $reportContent);
        echo "\n📄 Detailed report saved to: structure_report.txt\n";
        echo str_repeat("=", 60) . "\n";
    }
    
    public function run() {
        echo "\n🚀 Starting PHP Project Structure Analysis...\n";
        echo str_repeat("=", 60) . "\n";
        
        $this->checkDirectoryStructure();
        $this->analyzeFileTypes();
        $this->checkKeyFiles();
        $this->analyzeControllers();
        $this->analyzeModels();
        $this->analyzeHelpers();
        $this->analyzeAPIs();
        $this->analyzePublicUI();
        $this->checkComposer();
        $this->checkDatabaseConfig();
        $this->suggestImprovements();
        $this->generateReport();
        
        echo "\n✨ Analysis complete!\n";
    }
}

// Run the checker
$rootPath = $argv[1] ?? '.';
$checker = new ProjectStructureChecker($rootPath);
$checker->run();
?>