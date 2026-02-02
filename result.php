<?php
require_once "db_config.php";

// POSTデータの取得（q1, q2, q3）
$q1 = isset($_POST["q1"]) ? trim($_POST["q1"]) : "";
$q2 = isset($_POST["q2"]) ? trim($_POST["q2"]) : "";
$q3 = isset($_POST["q3"]) ? trim($_POST["q3"]) : "";

$errorMessage = "";
$selectedResort = null;

if ($q1 === "" || $q2 === "" || $q3 === "") {
    $errorMessage = "回答が不足しています。もう一度診断してください。";
}

// resorts テーブルを取得
$resorts = [];
if ($errorMessage === "") {
    $result = $conn->query("SELECT * FROM resorts");
    if (!$result) {
        $errorMessage = "リゾート情報の取得に失敗しました。";
    } else {
        while ($row = $result->fetch_assoc()) {
            $resorts[] = $row;
        }
        $result->free();
    }
}

// マッチングルール（キーワードは target / features に含まれる想定）
$matchRules = [
    "q1" => [
        "ski" => ["ski", "滑り", "滑走", "パウダー", "snow", "ゲレンデ"],
        "sightseeing" => ["温泉", "景色", "絶景", "観光", "雪景色", "街", "グルメ"]
    ],
    "q2" => [
        "beginner" => ["初心者", "初級", "緩やか", "ファミリー", "安心"],
        "advanced" => ["上級", "中級", "急斜面", "パーク", "コース", "チャレンジ"]
    ],
    "q3" => [
        "family" => ["家族", "子供", "キッズ", "ファミリー", "親子"],
        "solo" => ["友達", "一人", "ソロ", "カップル", "仲間", "デート"]
    ]
];

// キーワード一致のスコア計算
function countKeywordScore(string $text, array $keywords, int $weight): int
{
    $score = 0;
    foreach ($keywords as $keyword) {
        if ($keyword === "") {
            continue;
        }
        if (mb_stripos($text, $keyword) !== false) {
            $score += $weight;
        }
    }
    return $score;
}

function resolveResortImage(array $resort): string
{
    $keys = ["image", "image_url", "image_path", "bg_image", "background"];
    foreach ($keys as $key) {
        if (!empty($resort[$key])) {
            return (string)$resort[$key];
        }
    }
    return "image/header.JPEG";
}

function resolveResortName(array $resort): string
{
    $keys = ["name", "resort_name", "title"];
    foreach ($keys as $key) {
        if (!empty($resort[$key])) {
            return (string)$resort[$key];
        }
    }
    return "おすすめリゾート";
}

function resolveResortDescription(array $resort): string
{
    $keys = ["description", "features", "summary"];
    foreach ($keys as $key) {
        if (!empty($resort[$key])) {
            return (string)$resort[$key];
        }
    }
    return "あなたの旅スタイルに合うリゾートをセレクトしました。";
}

function resolveResortLink(array $resort, string $resortName): string
{
    $keys = ["link", "url", "page", "detail_url", "detail_page"];
    foreach ($keys as $key) {
        if (!empty($resort[$key])) {
            return (string)$resort[$key];
        }
    }
    $nameToAnchor = [
        "神立" => "resort-kandatsu",
        "かぐら" => "resort-kagura",
        "石打丸山" => "resort-ishiuchi",
        "苗場" => "resort-naeba",
        "ガーラ" => "resort-gala",
        "GALA" => "resort-gala"
    ];

    foreach ($nameToAnchor as $key => $anchor) {
        if ($resortName !== "" && mb_stripos($resortName, $key) !== false) {
            return "index.html#" . $anchor;
        }
    }

    return "index.html#ski";
}

function resolveResortDisplayName(string $resortName): string
{
    $nameMap = [
        "神立" => "神立スノーリゾート",
        "かぐら" => "かぐらスキー場",
        "石打丸山" => "ザ・ヴェランダ石打丸山",
        "苗場" => "苗場スキー場",
        "ガーラ" => "ガーラ湯沢スキー場",
        "GALA" => "ガーラ湯沢スキー場"
    ];

    foreach ($nameMap as $key => $displayName) {
        if ($resortName !== "" && mb_stripos($resortName, $key) !== false) {
            return $displayName;
        }
    }

    return $resortName;
}

function resolveResortLogo(string $resortName): string
{
    $logoMap = [
        "神立" => "image/logo_kandatsu.png",
        "かぐら" => "image/logo_kagura.png",
        "石打丸山" => "image/logo_ishiuchi.png",
        "苗場" => "image/logo_naeba.png",
        "ガーラ" => "image/logo_gala.png",
        "GALA" => "image/logo_gala.png"
    ];

    foreach ($logoMap as $key => $path) {
        if ($resortName !== "" && mb_stripos($resortName, $key) !== false) {
            return $path;
        }
    }

    return "";
}

if ($errorMessage === "" && count($resorts) === 0) {
    $errorMessage = "リゾート情報が見つかりませんでした。";
}

if ($errorMessage === "") {
    // 設計した8通りの組み合わせ（最優先で採用）
    $comboMap = [
        "ski|beginner|family" => "苗場",
        "ski|advanced|family" => "苗場",
        "sightseeing|beginner|family" => "ガーラ湯沢",
        "sightseeing|advanced|family" => "苗場",
        "ski|beginner|solo" => "石打",
        "ski|advanced|solo" => "かぐら",
        "sightseeing|beginner|solo" => "石打",
        "sightseeing|advanced|solo" => "神立"
    ];

    $comboKey = $q1 . "|" . $q2 . "|" . $q3;
    $mappedName = $comboMap[$comboKey] ?? "";

    // 組み合わせに一致したら、DBから該当スキー場を優先選択
    if ($mappedName !== "") {
        foreach ($resorts as $resort) {
            $name = resolveResortName($resort);
            if ($name !== "" && mb_stripos($name, $mappedName) !== false) {
                $selectedResort = $resort;
                break;
            }
        }
    }

    // 該当が見つからない場合は、従来のスコア方式で判定
    if ($selectedResort === null) {
    $scores = [];
    foreach ($resorts as $index => $resort) {
        // target と features を結合してスコア判定
        $target = isset($resort["target"]) ? (string)$resort["target"] : "";
        $features = isset($resort["features"]) ? (string)$resort["features"] : "";
        $targetLower = mb_strtolower($target);
        $featuresLower = mb_strtolower($features);

        $score = 0;

        // q1, q2, q3 各回答に対してキーワードをチェック
        $answers = ["q1" => $q1, "q2" => $q2, "q3" => $q3];
        foreach ($answers as $key => $answer) {
            if (!isset($matchRules[$key][$answer])) {
                continue;
            }
            $keywords = $matchRules[$key][$answer];
            // target は重要度が高いので重みを大きくする
            $score += countKeywordScore($targetLower, $keywords, 2);
            $score += countKeywordScore($featuresLower, $keywords, 1);

            // 直接一致があればボーナス
            if ($answer !== "" && mb_stripos($targetLower, $answer) !== false) {
                $score += 3;
            }
        }

        $scores[$index] = $score;
    }

    $maxScore = max($scores);
    $topResorts = [];
    foreach ($scores as $index => $score) {
        if ($score === $maxScore) {
            $topResorts[] = $resorts[$index];
        }
    }

    // 同点ならランダムで1つ選ぶ
    $selectedResort = $topResorts[array_rand($topResorts)];
    }
}

$resortName = $selectedResort ? resolveResortName($selectedResort) : "";
$resortDisplayName = $resortName !== "" ? resolveResortDisplayName($resortName) : "";
$resortLogo = $resortDisplayName !== "" ? resolveResortLogo($resortDisplayName) : "";
$resortDescription = $selectedResort ? resolveResortDescription($selectedResort) : "";
$resortTarget = $selectedResort && !empty($selectedResort["target"]) ? (string)$selectedResort["target"] : "";
$resortImage = $selectedResort ? resolveResortImage($selectedResort) : "image/header.JPEG";
$resortLink = $selectedResort ? resolveResortLink($selectedResort, $resortName) : "index.html#ski";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>診断結果 | 越後湯沢</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body.result-page {
            color: #ffffff;
            background-color: #0b1020;
        }

        .result-hero {
            min-height: 100vh;
            background-image: linear-gradient(rgba(10, 14, 20, 0.45), rgba(10, 14, 20, 0.55)), url("<?php echo htmlspecialchars($resortImage, ENT_QUOTES, "UTF-8"); ?>");
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 90px 8%;
        }

        .result-card {
            width: min(980px, 100%);
            background: rgba(20, 28, 40, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 22px;
            padding: 48px clamp(28px, 5vw, 60px);
            backdrop-filter: blur(18px);
            box-shadow: 0 20px 60px rgba(9, 15, 25, 0.55);
            text-align: left;
        }

        .result-eyebrow {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            letter-spacing: 0.32em;
            margin-bottom: 16px;
        }

        .result-title-row {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: nowrap;
            margin-bottom: 12px;
            justify-content: space-between;
        }

        .result-logo {
            height: 270px;
            width: auto;
            max-width: 270px;
            object-fit: contain;
            filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.35));
        }

        .result-title {
            font-size: clamp(28px, 4vw, 40px);
            letter-spacing: 0.12em;
            margin-bottom: 0;
            text-shadow: 0 6px 20px rgba(0, 0, 0, 0.45);
            flex: 1 1 auto;
            min-width: 0;
        }

        .result-target {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 0.08em;
            margin-bottom: 22px;
        }

        .result-description {
            font-size: 16px;
            line-height: 2.2;
            color: rgba(255, 255, 255, 0.88);
            margin-bottom: 32px;
        }

        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        .result-button {
            display: inline-block;
            padding: 12px 32px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.75);
            color: #1f2a36;
            text-decoration: none;
            letter-spacing: 0.15em;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 12px 28px rgba(12, 20, 35, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .result-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(12, 20, 35, 0.45);
            background: rgba(255, 255, 255, 0.9);
        }

        @media (max-width: 640px) {
            .result-card {
                padding: 36px 24px;
            }

            .result-actions {
                flex-direction: column;
            }

            .result-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body class="result-page">
    <section class="result-hero">
        <div class="result-card">
            <?php if ($errorMessage !== ""): ?>
                <p class="result-eyebrow">ERROR</p>
                <h1 class="result-title">診断に失敗しました</h1>
                <p class="result-description"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>
                <div class="result-actions">
                    <a class="result-button" href="quiz.php">もう一度診断する</a>
                    <a class="result-button" href="index.html#ski">Back</a>
                </div>
            <?php else: ?>
                <p class="result-eyebrow">あなたにぴったりのスキー場は...</p>
                <div class="result-title-row">
                    <h1 class="result-title"><?php echo htmlspecialchars($resortDisplayName, ENT_QUOTES, "UTF-8"); ?></h1>
                    <?php if ($resortLogo !== ""): ?>
                        <img class="result-logo" src="<?php echo htmlspecialchars($resortLogo, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($resortDisplayName, ENT_QUOTES, "UTF-8"); ?>">
                    <?php endif; ?>
                </div>
                <?php if ($resortTarget !== ""): ?>
                    <p class="result-target"><?php echo htmlspecialchars($resortTarget, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>
                <p class="result-description"><?php echo nl2br(htmlspecialchars($resortDescription, ENT_QUOTES, "UTF-8")); ?></p>
                <div class="result-actions">
                    <a class="result-button" href="<?php echo htmlspecialchars($resortLink, ENT_QUOTES, "UTF-8"); ?>">スキー場をもっと知る</a>
                    <a class="result-button" href="quiz.php">もう一度診断する</a>
                    <a class="result-button" href="index.html#ski">Back</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>
