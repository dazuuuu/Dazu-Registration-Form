<?php
// create_upload_folders.php
$directories = [
    $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/',
    $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/services/',
    $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/services/covers/',
    $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/services/sections/',
    $_SERVER['DOCUMENT_ROOT'] . '/Realestate/public/uploads/services/gallery/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo "Created: $dir<br>";
    }
}
echo "All directories created!";
?>