<?php
// DB接続設定（必要に応じて編集してください）
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "password";
$dbName = "echigo_yuzawa_db";

// 文字コードはUTF-8で統一
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    // 接続失敗時は処理を中断
    die("DB接続に失敗しました: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
