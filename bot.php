<?php
// CONFIGURATION
define('API_KEY', '7559916614:AAGbxwOQMpU8U0KJAJb8dzwFk_CDUtxr0EU');
$admin = 7342925788;

function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return json_decode(curl_exec($ch), true);
}

// UPDATE PARSING
$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"] ?? null;
$text = $message["text"] ?? '';
$cid = $message["chat"]["id"] ?? '';
$uid = $message["from"]["id"] ?? '';
$name = $message["from"]["first_name"] ?? '';

// STEP AND DAY DETECTION
$step_file = "steps/$uid.txt";
$day_file = "day.txt";
if (!file_exists($day_file)) file_put_contents($day_file, "1");
$current_day = file_get_contents($day_file);

// RESPONSE HANDLERS
function getQuestion($day, $index) {
    $file = "questions/$day.txt";
    if (!file_exists($file)) return null;
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    return $lines[$index] ?? null;
}

function getAnswer($day, $index) {
    $file = "answers/$day.txt";
    if (!file_exists($file)) return null;
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    return trim(strtolower($lines[$index] ?? ''));
}

function countQuestions($day) {
    $file = "questions/$day.txt";
    if (!file_exists($file)) return 0;
    return count(file($file, FILE_IGNORE_NEW_LINES));
}

function checkAnswer($input, $correct) {
    return strtolower(trim($input)) === strtolower(trim($correct));
}

// START COMMAND
if ($text == "/start") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "🤖 Salom, $name!
Bu bot orqali har kuni Zakovat mashg'ulotlarini bajarasiz.

Yuboring:
1 - 1-kun savollari
2 - 2-kun savollari
/admin - admin menyusi",
    ]);
}

// ADMIN PANEL
elseif ($text == "/admin" && $uid == $admin) {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "🔐 Admin panel",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => '➕ Yangi kun qo‘shish']],
                [['text' => '📢 Xabar yuborish'], ['text' => '📊 Statistika']],
                [['text' => '/start']]
            ], 'resize_keyboard' => true
        ])
    ]);
}

// ADMIN YANGI KUN QO‘SHISH
elseif ($text == '➕ Yangi kun qo‘shish' && $uid == $admin) {
    file_put_contents("admin_step.txt", "savollar");
    file_put_contents("admin_temp.txt", "");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "📝 Savollarni kiriting. Har birini alohida yuboring. '✅ Tayyor' deb yozganda tugaydi."]);
}
elseif (file_exists("admin_step.txt") && $uid == $admin) {
    $step = file_get_contents("admin_step.txt");
    if ($step == "savollar" && $text != '✅ Tayyor') {
        file_put_contents("admin_temp.txt", $text . PHP_EOL, FILE_APPEND);
    } elseif ($step == "savollar" && $text == '✅ Tayyor') {
        $day = (int)file_get_contents($day_file) + 1;
        rename("admin_temp.txt", "questions/$day.txt");
        file_put_contents("admin_step.txt", "javoblar");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Endi javoblarni kiriting (xuddi shunday tartibda). Tugaganda '✅ Tayyor' deb yozing."]);
    } elseif ($step == "javoblar" && $text != '✅ Tayyor') {
        file_put_contents("admin_temp.txt", $text . PHP_EOL, FILE_APPEND);
    } elseif ($step == "javoblar" && $text == '✅ Tayyor') {
        $day = (int)file_get_contents($day_file) + 1;
        rename("admin_temp.txt", "answers/$day.txt");
        file_put_contents($day_file, $day);
        unlink("admin_step.txt");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "🎉 $day-kun savollari qo‘shildi. Barcha foydalanuvchilarga xabar yuborilmoqda."]);

        $users = glob("scores/*.txt");
        foreach ($users as $user_file) {
            $user_id = basename($user_file, ".txt");
            bot('sendMessage', [
                'chat_id' => $user_id,
                'text' => "🆕 $day-kun Zakovat savollari tayyor! Botga $day deb yuboring."
            ]);
        }
    }
}

// TEST BOSHLASH
elseif (is_numeric($text)) {
    $day = intval($text);
    if (!file_exists("questions/$day.txt")) {
        bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ Bunday kun topilmadi."]);
    } else {
        file_put_contents($step_file, "0|$day");
        bot('sendMessage', ['chat_id' => $cid, 'text' => getQuestion($day, 0)]);
    }
}

// JAVOB TEKSHIRISH
elseif (file_exists($step_file)) {
    [$step, $day] = explode('|', file_get_contents($step_file));
    $step = (int)$step;
    $total = countQuestions($day);

    $trueAns = getAnswer($day, $step);
    $isCorrect = checkAnswer($text, $trueAns);

    $resultsFile = "results/{$uid}_$day.txt";
    $scoreFile = "scores/$uid.txt";

    if (!file_exists($resultsFile)) file_put_contents($resultsFile, "");

    if ($isCorrect) {
        file_put_contents($resultsFile, "✅ $text\n", FILE_APPEND);
        $score = file_exists($scoreFile) ? file_get_contents($scoreFile) : 0;
        file_put_contents($scoreFile, $score + 1);
        bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ To‘g‘ri javob!"]);
    } else {
        file_put_contents($resultsFile, "❌ $text\n", FILE_APPEND);
        bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ Noto‘g‘ri. To‘g‘ri javob: $trueAns"]);
    }

    $step++;
    if ($step < $total) {
        file_put_contents($step_file, "$step|$day");
        bot('sendMessage', ['chat_id' => $cid, 'text' => getQuestion($day, $step)]);
    } else {
        unlink($step_file);

        $lines = file($resultsFile, FILE_IGNORE_NEW_LINES);
        $correct = count(array_filter($lines, fn($l) => strpos($l, '✅') === 0));
        $wrong = count(array_filter($lines, fn($l) => strpos($l, '❌') === 0));
        $percent = round(($correct / ($correct + $wrong)) * 100, 1);

        $summary = "📊 $day-kun natijalari:\n======================\n";
        $summary .= "✅ To‘g‘ri javoblar: $correct\n❌ Noto‘g‘ri javoblar: $wrong\n📈 Umumiy foiz: $percent%\n";
        if ($percent < 20) {
            $summary .= "\n⚠️ Diqqat! Natijangiz juda past. Harakat qiling!";
        } else {
            $summary .= "\n💡 Zo‘r! Yaxshi ishladingiz.";
        }
        bot('sendMessage', ['chat_id' => $cid, 'text' => $summary]);
    }
}
?>
