<?php
// =======================
// ZAKOVAT BOT V2 — BY DAVRONBEK
// =======================

// TOKEN va ADMIN ID
const API_KEY = '7559916614:AAGbxwOQMpU8U0KJAJb8dzwFk_CDUtxr0EU';
const ADMIN_ID = 7342925788;

// CURL orqali Telegramga murojaat funksiyasi
function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    return json_decode(curl_exec($ch), true);
}

// Foydalanuvchi bilan bog'liq update
$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

// Agar xabar kelsa
if ($message) {
    $cid = $message["chat"]["id"];
    $mid = $message["message_id"];
    $text = trim($message["text"] ?? "");
    $name = $message["from"]["first_name"];
    
    if (!file_exists("users.txt")) file_put_contents("users.txt", "");
    if (strpos(file_get_contents("users.txt"), "$cid") === false) {
        file_put_contents("users.txt", "$cid\n", FILE_APPEND);
    }

    // Boshlanishi
    if ($text == "/start") {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "🤖 *Assalomu alaykum, $name! Zakovat botga xush kelibsiz.*\n\n" .
                      "Bot sizga har kuni 1 soatlik aqliy mashg'ulotlar beradi.\n" .
                      "Savollarga javob berasiz, oxirida esa natijalaringizni ko'rasiz.\n\n" .
                      "*Yozing:*\n1 — 1-kun savollari\n2 — 2-kun savollari va hokazo",
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "📊 Reyting"]]],
                'resize_keyboard' => true
            ])
        ]);
    }

    // Reyting tugmasi
    elseif ($text == "📊 Reyting") {
        $scores = file_exists("scores.json") ? json_decode(file_get_contents("scores.json"), true) : [];
        arsort($scores);
        $top = array_slice($scores, 0, 10, true);
        $res = "🏆 *Top 10 Reyting:*\n";
        foreach ($top as $id => $score) {
            $res .= "[`$id`](tg://user?id=$id) — $score%\n";
        }
        bot('sendMessage', ['chat_id' => $cid, 'text' => $res, 'parse_mode' => 'markdown']);
    }

    // Admin panel
    elseif ($text == "/admin" && $cid == ADMIN_ID) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "🔐 Admin panel:",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [["text" => "➕ Yangi kun qo‘shish"]],
                    [["text" => "📢 Xabar yuborish"], ["text" => "📊 Statistika"]]
                ],
                'resize_keyboard' => true
            ])
        ]);
    }

    // Boshqa yozilgan raqam — masalan: 1, 2, 3 kun
    elseif (is_numeric($text)) {
        $day = intval($text);
        if (file_exists("questions/$day.json")) {
            $questions = json_decode(file_get_contents("questions/$day.json"), true);
            $answers = json_decode(file_get_contents("answers/$day.json"), true);
            file_put_contents("steps/$cid.json", json_encode([
                'day' => $day,
                'index' => 0,
                'score' => 0,
                'total' => count($questions)
            ]));
            bot('sendMessage', ['chat_id' => $cid, 'text' => "🧠 Savol 1:\n" . $questions[0]]);
        } else {
            bot('sendMessage', ['chat_id' => $cid, 'text' => "⚠️ Bu kun uchun savollar hali tayyor emas."]);
        }
    }

    // Javob tekshirish
    elseif (file_exists("steps/$cid.json")) {
        $data = json_decode(file_get_contents("steps/$cid.json"), true);
        $questions = json_decode(file_get_contents("questions/{$data['day']}.json"), true);
        $answers = json_decode(file_get_contents("answers/{$data['day']}.json"), true);
        $index = $data['index'];

        $correct = mb_strtolower(trim($answers[$index]));
        $user_ans = mb_strtolower(trim($text));

        if ($user_ans == $correct) {
            $data['score']++;
            bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ To‘g‘ri!"]);
        } else {
            bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ Noto‘g‘ri!\nTo‘g‘ri javob: $correct"]);
        }

        $data['index']++;

        if ($data['index'] < $data['total']) {
            file_put_contents("steps/$cid.json", json_encode($data));
            bot('sendMessage', ['chat_id' => $cid, 'text' => "🧠 Savol " . ($data['index'] + 1) . ":\n" . $questions[$data['index']]]);
        } else {
            unlink("steps/$cid.json");
            $foiz = round(($data['score'] / $data['total']) * 100);
            $msg = "📊 Natija: $foiz%\nTo‘g‘ri: {$data['score']} ta\nXato: " . ($data['total'] - $data['score']) . " ta";
            if ($foiz < 20) $msg .= "\n⚠️ Iltimos, ko‘proq harakat qiling. Aks holda bloklanishingiz mumkin.";
            bot('sendMessage', ['chat_id' => $cid, 'text' => $msg]);

            $scores = file_exists("scores.json") ? json_decode(file_get_contents("scores.json"), true) : [];
            $scores[$cid] = $foiz;
            file_put_contents("scores.json", json_encode($scores));
        }
    }

    // Admin yangi kun savollari
    elseif ($text == "➕ Yangi kun qo‘shish" && $cid == ADMIN_ID) {
        file_put_contents("steps/$cid-admin.txt", "await_day");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "📅 Yangi kun raqamini yuboring:"]);
    }
    elseif (file_exists("steps/$cid-admin.txt")) {
        $step = file_get_contents("steps/$cid-admin.txt");

        if ($step == "await_day") {
            file_put_contents("steps/$cid-admin.txt", "day-$text-0");
            file_put_contents("questions/$text.json", json_encode([]));
            file_put_contents("answers/$text.json", json_encode([]));
            bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ 1-savolni kiriting:"]);
        }
        elseif (preg_match("/day-(\d+)-(\d+)/", $step, $m)) {
            $day = $m[1];
            $idx = intval($m[2]);
            $q = json_decode(file_get_contents("questions/$day.json"), true);
            $q[] = $text;
            file_put_contents("questions/$day.json", json_encode($q));
            file_put_contents("steps/$cid-admin.txt", "day-ans-$day-$idx");
            bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ Ushbu savolning javobini kiriting:"]);
        }
        elseif (preg_match("/day-ans-(\d+)-(\d+)/", $step, $m)) {
            $day = $m[1];
            $a = json_decode(file_get_contents("answers/$day.json"), true);
            $a[] = $text;
            file_put_contents("answers/$day.json", json_encode($a));
            $idx = intval($m[2]) + 1;
            if ($idx < 7) {
                file_put_contents("steps/$cid-admin.txt", "day-$day-$idx");
                bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ " . ($idx + 1) . "-savolni kiriting:"]);
            } else {
                unlink("steps/$cid-admin.txt");
                $users = explode("\n", trim(file_get_contents("users.txt")));
                foreach ($users as $u) {
                    bot('sendMessage', ['chat_id' => $u, 'text' => "📢 *$day-kun savollari tayyor!*\nYozing: $day", 'parse_mode' => 'markdown']);
                }
                bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ $day-kun saqlandi va foydalanuvchilarga yuborildi."]);
            }
        }
    }

    // Xabar yuborish (tugmali holatda yoziladi)
    elseif ($text == "📢 Xabar yuborish" && $cid == ADMIN_ID) {
        file_put_contents("steps/$cid-admin.txt", "broadcast");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "📨 Yubormoqchi bo‘lgan xabaringizni yozing:"]);
    }
    elseif (file_exists("steps/$cid-admin.txt") && file_get_contents("steps/$cid-admin.txt") == "broadcast") {
        $users = explode("\n", trim(file_get_contents("users.txt")));
        foreach ($users as $u) {
            bot('sendMessage', ['chat_id' => $u, 'text' => $text]);
        }
        unlink("steps/$cid-admin.txt");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Xabar barcha foydalanuvchilarga yuborildi."]);
    }
}
?>
