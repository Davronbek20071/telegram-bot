<?php
define('API_KEY', '7559916614:AAGbxwOQMpU8U0KJAJb8dzwFk_CDUtxr0EU');
$admin = 7342925788; // Admin ID

function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/$method";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    return json_decode(curl_exec($ch), true);
}

$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"] ?? null;
if (!$message) exit;
$text = trim($message["text"]);
$cid = $message["chat"]["id"];
$name = $message["from"]["first_name"];
$uid = $message["from"]["id"];

if (!file_exists("users.txt")) file_put_contents("users.txt", "");
$users = explode("\n", trim(file_get_contents("users.txt")));
if (!in_array($uid, $users)) file_put_contents("users.txt", "$uid\n", FILE_APPEND);

$stepFile = "steps/$uid.txt";
$scoreFile = "scores/$uid.txt";
$resultFile = "results/$uid.txt";

function sendMessage($cid, $text, $buttons = []) {
    $keyboard = $buttons ? ['keyboard' => $buttons, 'resize_keyboard' => true] : ['remove_keyboard' => true];
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $text,
        'reply_markup' => json_encode($keyboard)
    ]);
}

function showTop10() {
    $files = glob("scores/*.txt");
    $ratings = [];
    foreach ($files as $file) {
        $id = basename($file, ".txt");
        $ratings[$id] = intval(trim(file_get_contents($file)));
    }
    arsort($ratings);
    $top = array_slice($ratings, 0, 10, true);
    $text = "🏆 *Top 10 Reyting:*\n\n";
    $i = 1;
    foreach ($top as $id => $score) {
        $text .= "$i. [$id](tg://user?id=$id) — $score ball\n";
        $i++;
    }
    return $text;
}

if ($text == "/start") {
    sendMessage($cid, "👋 Salom, $name!\nBu Zakovat bot. Har kuni siz uchun 1 soatlik mantiqiy-intellektual trening beriladi.\n\n- Savollarga javob yozing\n- “Javobni ko‘rish” tugmasi orqali javobni ko‘ring\n- Test oxirida natijangiz, foizingiz chiqadi\n- Top 10 reytingda bo‘lishga harakat qiling!\n\nYozing: 1 yoki 2 yoki 3...");
} elseif (is_numeric($text)) {
    $day = intval($text);
    if (!file_exists("questions/$day.txt") || !file_exists("answers/$day.txt")) {
        sendMessage($cid, "❌ Bu kunga savollar hali qo‘shilmagan.");
        return;
    }
    file_put_contents($stepFile, "0|$day");
    file_put_contents($scoreFile, "0");
    file_put_contents($resultFile, "");
    $questions = explode("\n", trim(file_get_contents("questions/$day.txt")));
    sendMessage($cid, "🧠 Savol 1:\n" . $questions[0], [["Javobni ko‘rish"], ["Top 10"]]);
} elseif ($text == "Javobni ko‘rish") {
    if (!file_exists($stepFile)) return;
    [$step, $day] = explode("|", file_get_contents($stepFile));
    $questions = explode("\n", trim(file_get_contents("questions/$day.txt")));
    $answers = explode("\n", trim(file_get_contents("answers/$day.txt")));
    $score = intval(file_get_contents($scoreFile));

    $results = file_exists($resultFile) ? explode("\n", trim(file_get_contents($resultFile))) : [];

    $results[$step] = "❌ Javob yo‘q";
    file_put_contents($resultFile, implode("\n", $results));

    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ To‘g‘ri javob:\n" . $answers[$step]]);

    $step++;
    if ($step < count($questions)) {
        file_put_contents($stepFile, "$step|$day");
        sendMessage($cid, "🧠 Savol " . ($step + 1) . ":\n" . $questions[$step], [["Javobni ko‘rish"], ["Top 10"]]);
    } else {
        unlink($stepFile);
        $foiz = intval(($score / count($questions)) * 100);
        $xabar = "🎉 Mashg‘ulot tugadi!\n\n";
        $natija = explode("\n", trim(file_get_contents($resultFile)));
        foreach ($natija as $i => $res) $xabar .= ($i+1) . ") $res\n";
        $xabar .= "\n🔢 Foiz: $foiz%\n";
        if ($foiz < 20) $xabar .= "❗Natijangiz juda past. Keyingi safar harakat qiling, aks holda bloklanishingiz mumkin!";
        file_put_contents($scoreFile, $score);
        sendMessage($cid, $xabar);
    }
} elseif ($text == "Top 10") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => showTop10(),
        'parse_mode' => "Markdown"
    ]);
} elseif ($text == "/admin" && $uid == $admin) {
    sendMessage($cid, "🛠 Admin panel:", [["📣 Xabar yuborish"], ["📊 Statistika"]]);
} elseif ($text == "📣 Xabar yuborish" && $uid == $admin) {
    file_put_contents("steps/$uid.txt", "sendmsg");
    sendMessage($cid, "✍️ Yubormoqchi bo‘lgan xabaringizni yozing:");
} elseif (file_exists("steps/$uid.txt") && file_get_contents("steps/$uid.txt") == "sendmsg") {
    $all = explode("\n", trim(file_get_contents("users.txt")));
    foreach ($all as $id) {
        bot('sendMessage', ['chat_id' => $id, 'text' => "📢 Admindan xabar:\n\n$text"]);
    }
    unlink("steps/$uid.txt");
    sendMessage($cid, "✅ Xabar yuborildi.");
} elseif ($text == "📊 Statistika" && $uid == $admin) {
    $usercount = count(file("users.txt"));
    $scorefiles = glob("scores/*.txt");
    $totalScore = 0;
    foreach ($scorefiles as $file) $totalScore += intval(file_get_contents($file));
    $avg = count($scorefiles) ? round($totalScore / count($scorefiles), 2) : 0;
    sendMessage($cid, "📊 Statistika:\n👥 Foydalanuvchilar: $usercount\n📈 O‘rtacha ball: $avg");
} else {
    if (file_exists($stepFile)) {
        [$step, $day] = explode("|", file_get_contents($stepFile));
        $answers = explode("\n", trim(file_get_contents("answers/$day.txt")));
        $javob = strtolower(trim($text));
        $togri = strtolower(trim($answers[$step]));

        $results = file_exists($resultFile) ? explode("\n", trim(file_get_contents($resultFile))) : [];

        if ($javob == $togri) {
            $results[$step] = "✅ $text";
            $score = intval(file_get_contents($scoreFile)) + 1;
            file_put_contents($scoreFile, $score);
            bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ To‘g‘ri!"]);
        } else {
            $results[$step] = "❌ $text";
            bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ Noto‘g‘ri."]);
        }

        file_put_contents($resultFile, implode("\n", $results));

        $step++;
        $questions = explode("\n", trim(file_get_contents("questions/$day.txt")));
        if ($step < count($questions)) {
            file_put_contents($stepFile, "$step|$day");
            sendMessage($cid, "🧠 Savol " . ($step + 1) . ":\n" . $questions[$step], [["Javobni ko‘rish"], ["Top 10"]]);
        } else {
            unlink($stepFile);
            $foiz = intval((intval(file_get_contents($scoreFile)) / count($questions)) * 100);
            $xabar = "🎉 Mashg‘ulot tugadi!\n\n";
            foreach ($results as $i => $res) $xabar .= ($i+1) . ") $res\n";
            $xabar .= "\n🔢 Foiz: $foiz%\n";
            if ($foiz < 20) $xabar .= "❗ Natijangiz past. Harakat qiling!";
            sendMessage($cid, $xabar);
        }
    }
}
?>
