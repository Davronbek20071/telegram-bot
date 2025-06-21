<?php
define('API_KEY', '7559916614:AAGbxwOQMpU8U0KJAJb8dzwFk_CDUtxr0EU');
$admin = 7342925788;

// FUNKSIYA
function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/$method";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return json_decode(curl_exec($ch), true);
}

// KELGAN XABARLARNI OLISh
$update = json_decode(file_get_contents("php://input"), true);
$message = $update['message'] ?? null;
$text = $message['text'] ?? '';
$cid = $message['chat']['id'] ?? '';
$name = $message['from']['first_name'] ?? '';
$mid = $message['message_id'] ?? '';

// USERLARNI SAQLAYMIZ
if (!file_exists("users.txt")) file_put_contents("users.txt", "");
$users = explode("\n", trim(file_get_contents("users.txt")));
if (!in_array($cid, $users)) {
    file_put_contents("users.txt", "$cid\n", FILE_APPEND);
}

// ADMIN BUYRUG'I
if ($cid == $admin && $text == "/admin") {
    file_put_contents("admin_step.txt", "awaiting_day");
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "📅 Qaysi kun uchun savollar kiritmoqchisiz? (Masalan: 3)",
        'reply_markup' => json_encode(['remove_keyboard' => true])
    ]);
    exit;
}

// ADMIN JARAYONI
$admin_step = file_exists("admin_step.txt") ? file_get_contents("admin_step.txt") : '';
$admin_day = file_exists("admin_day.txt") ? file_get_contents("admin_day.txt") : '';

if ($cid == $admin && is_numeric($text) && $admin_step == "awaiting_day") {
    file_put_contents("admin_day.txt", $text);
    file_put_contents("admin_step.txt", "awaiting_question");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ Endi $text-kun savollarini kiriting."]);
    exit;
}

if ($cid == $admin && $admin_step == "awaiting_question") {
    file_put_contents("questions/{$admin_day}.txt", $text);
    file_put_contents("admin_step.txt", "awaiting_answer");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Savollar saqlandi.\nEndi javoblarni kiriting."]);
    exit;
}

if ($cid == $admin && $admin_step == "awaiting_answer") {
    file_put_contents("answers/{$admin_day}.txt", $text);
    unlink("admin_step.txt");
    unlink("admin_day.txt");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Javoblar saqlandi. Foydalanuvchilarga habar yuborildi."]);

    // FOYDALANUVCHILARGA HABAR
    foreach ($users as $u) {
        if ($u != "") {
            bot('sendMessage', [
                'chat_id' => $u,
                'text' => "📢 $admin_day-kun Zakovat savollari joylandi!\nBotga faqat raqam $admin_day ni yuboring.",
                'reply_markup' => json_encode(['remove_keyboard' => true])
            ]);
        }
    }
    exit;
}

// FOYDALANUVCHI RAQAM YUBORGANDA
if (is_numeric($text) && file_exists("questions/$text.txt")) {
    file_put_contents("steps/step_$cid.txt", "$text|0");
    $lines = explode("\n", trim(file_get_contents("questions/$text.txt")));
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "🧠 $text-kun Zakovat mashg‘uloti boshlandi.\n\n" . $lines[0],
        'reply_markup' => json_encode(['keyboard' => [[['text' => "Javobni ko‘rish"]]], 'resize_keyboard' => true])
    ]);
    exit;
}

// FOYDALANUVCHI "Javobni ko‘rish" bosganida
if ($text == "Javobni ko‘rish") {
    $step_file = "steps/step_$cid.txt";
    if (!file_exists($step_file)) {
        bot('sendMessage', ['chat_id' => $cid, 'text' => "ℹ️ Iltimos, avval raqam yuboring."]);
        exit;
    }

    [$day, $index] = explode('|', file_get_contents($step_file));
    $questions = explode("\n", trim(file_get_contents("questions/$day.txt")));
    $answers = explode("\n", trim(file_get_contents("answers/$day.txt")));

    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Javob:\n" . ($answers[$index] ?? "❓ Javob topilmadi.")]);
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ O‘zingizni tekshiring. Xatoni qayd eting."]);

    $index++;
    if ($index < count($questions)) {
        file_put_contents($step_file, "$day|$index");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => $questions[$index],
            'reply_markup' => json_encode(['keyboard' => [[['text' => "Javobni ko‘rish"]]], 'resize_keyboard' => true])
        ]);
    } else {
        unlink($step_file);
        bot('sendMessage', ['chat_id' => $cid, 'text' => "🎉 $day-kun mashg‘uloti tugadi! Ertangi kun uchun tayyor turing."]);
    }
}
?>
