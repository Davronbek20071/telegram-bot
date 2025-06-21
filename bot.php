<?php
define('API_KEY', '7559916614:AAGbxwOQMpU8U0KJAJb8dzwFk_CDUtxr0EU');
$admin = 7342925788;

function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"];
$cid = $message["chat"]["id"];
$mid = $message["message_id"];
$name = $message["from"]["first_name"];
$text = trim($message["text"]);

@mkdir("questions");
@mkdir("answers");
@mkdir("results");

if (!file_exists("users.txt")) file_put_contents("users.txt", "");
$users = explode("\n", trim(file_get_contents("users.txt")));
if (!in_array($cid, $users)) {
    file_put_contents("users.txt", $cid."\n", FILE_APPEND);
}

function save_result($cid, $day, $correct, $wrong) {
    $total = $correct + $wrong;
    $percent = ($total > 0) ? round(($correct / $total) * 100) : 0;
    $text = "📊 <b>$day-kun Zakovat natijalari</b>\n";
    $text .= "✅ To‘g‘ri: $correct ta\n❌ Noto‘g‘ri: $wrong ta\n";
    $text .= "📈 Foiz: $percent%\n";
    if ($percent < 20) {
        $text .= "⚠️ Juda past natija. Harakat qiling, aks holda botdan chiqarilishingiz mumkin!";
    }
    return $text;
}

function load_qa($day) {
    $q = @file("questions/day$day.txt", FILE_IGNORE_NEW_LINES);
    $a = @file("answers/day$day.txt", FILE_IGNORE_NEW_LINES);
    return [$q, $a];
}

function save_score($cid, $name, $score) {
    $scores = file_exists("scores.json") ? json_decode(file_get_contents("scores.json"), true) : [];
    $scores["$cid"] = ["name" => $name, "score" => $score];
    file_put_contents("scores.json", json_encode($scores));
}

function top10() {
    $scores = file_exists("scores.json") ? json_decode(file_get_contents("scores.json"), true) : [];
    uasort($scores, fn($a, $b) => $b['score'] - $a['score']);
    $res = "🏆 <b>Top 10 reyting</b>\n\n";
    $i = 1;
    foreach ($scores as $u) {
        $res .= "$i. ".$u['name']." — ".$u['score']." ball\n";
        if (++$i > 10) break;
    }
    return $res;
}

if ($text == "/start") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "👋 Salom, $name!\nBu Zakovat bot.\n\n🧠 Har kuni 1 soatlik intellektual trening olasiz.\n📌 Kun raqamini (1, 2, 3...) yozing.\n📊 /top - reyting\n/help - ma'lumot",
        'parse_mode' => "HTML"
    ]);
}
elseif ($text == "/help") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "ℹ️ Har kuni botga 1, 2, 3 kabi raqam yuboring.\nJavobingizni yozing yoki 'Javobni ko‘rish'ni bosing.\nOxirida statistika va reyting chiqadi.",
    ]);
}
elseif ($text == "/top") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => top10(),
        'parse_mode' => "HTML"
    ]);
}
elseif (is_numeric($text) && $text > 0) {
    $day = (int)$text;
    list($qs, $as) = load_qa($day);
    if (!$qs || !$as) {
        bot('sendMessage', ['chat_id' => $cid, 'text' => "⚠️ $day-kun savollari hali yuklanmagan."]);
    } else {
        file_put_contents("results/$cid-day.txt", "$day|0|0|0");
        file_put_contents("steps/$cid.txt", "0|$day");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "📅 $day-kun savollari boshlandi.\n\nSavol 1:\n" . $qs[0],
            'reply_markup' => json_encode([
                'keyboard' => [["Javobni ko‘rish"]],
                'resize_keyboard' => true
            ])
        ]);
    }
}
elseif ($text == "Javobni ko‘rish") {
    if (!file_exists("steps/$cid.txt")) return;
    list($n, $day) = explode("|", file_get_contents("steps/$cid.txt"));
    $n = (int)$n;
    list($qs, $as) = load_qa($day);
    list($d, $c, $w, $score) = explode("|", file_get_contents("results/$cid-day.txt"));
    $w++;
    file_put_contents("results/$cid-day.txt", "$day|$c|$w|$score");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ Siz javob bermadingiz.\n✅ To‘g‘ri javob: ".$as[$n]]);
    $n++;
    if ($n < count($qs)) {
        file_put_contents("steps/$cid.txt", "$n|$day");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "Savol ".($n+1).":\n".$qs[$n]]);
    } else {
        unlink("steps/$cid.txt");
        $res = file_get_contents("results/$cid-day.txt");
        list($_, $correct, $wrong, $_score) = explode("|", $res);
        $score = (int)$correct * 10;
        save_score($cid, $name, $score);
        bot('sendMessage', ['chat_id' => $cid, 'text' => save_result($cid, $day, $correct, $wrong), 'parse_mode' => "HTML"]);
    }
}
elseif ($cid == $admin && $text == "/admin") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "👨‍💼 Admin panel:",
        'reply_markup' => json_encode([
            'keyboard' => [
                ["➕ Kunlik savol-javob kiritish"],
                ["📢 Xabar yuborish", "📊 Statistika"]
            ],
            'resize_keyboard' => true
        ])
    ]);
}
elseif ($cid == $admin && $text == "➕ Kunlik savol-javob kiritish") {
    file_put_contents("adminstep.txt", "savol");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "Nechinchi kun uchun? (faqat raqam)"]);
}
elseif ($cid == $admin && is_numeric($text) && file_get_contents("adminstep.txt") == "savol") {
    file_put_contents("admin_day.txt", $text);
    file_put_contents("adminstep.txt", "savolmatn");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "Savollar matnini kiriting (yangi qator bilan bo‘lsin)"]);
}
elseif ($cid == $admin && file_get_contents("adminstep.txt") == "savolmatn") {
    $day = file_get_contents("admin_day.txt");
    file_put_contents("questions/day$day.txt", $text);
    file_put_contents("adminstep.txt", "javobmatn");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Savollar qabul qilindi.\nEndi javoblarni yuboring (yangi qator bilan)"]);
}
elseif ($cid == $admin && file_get_contents("adminstep.txt") == "javobmatn") {
    $day = file_get_contents("admin_day.txt");
    file_put_contents("answers/day$day.txt", $text);
    file_put_contents("adminstep.txt", "");
    $users = explode("\n", trim(file_get_contents("users.txt")));
    foreach ($users as $u) {
        if ($u) bot('sendMessage', ['chat_id' => $u, 'text' => "🆕 $day-kunlik savollar yuklandi!\nYuboring: <b>$day</b>", 'parse_mode' => 'HTML']);
    }
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ $day-kun savol va javoblari saqlandi va xabar yuborildi!"]);
}
elseif ($cid == $admin && $text == "📢 Xabar yuborish") {
    file_put_contents("adminstep.txt", "broadcast");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "Yuboriladigan matnni yozing"]);
}
elseif ($cid == $admin && file_get_contents("adminstep.txt") == "broadcast") {
    $users = explode("\n", trim(file_get_contents("users.txt")));
    foreach ($users as $u) {
        if ($u) bot('sendMessage', ['chat_id' => $u, 'text' => $text]);
    }
    file_put_contents("adminstep.txt", "");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Xabar yuborildi"]);
}
elseif ($cid == $admin && $text == "📊 Statistika") {
    $users = explode("\n", trim(file_get_contents("users.txt")));
    $total = count($users);
    $msg = "📈 Foydalanuvchilar soni: <b>$total ta</b>\n";
    bot('sendMessage', ['chat_id' => $cid, 'text' => $msg, 'parse_mode' => "HTML"]);
}
else {
    if (file_exists("steps/$cid.txt")) {
        list($n, $day) = explode("|", file_get_contents("steps/$cid.txt"));
        $n = (int)$n;
        list($qs, $as) = load_qa($day);
        $user_ans = strtolower(trim($text));
        $correct_ans = strtolower(trim($as[$n]));
        $result = ($user_ans == $correct_ans);
        $resfile = "results/$cid-day.txt";
        list($_d, $c, $w, $score) = explode("|", file_get_contents($resfile));
        if ($result) $c++; else $w++;
        file_put_contents($resfile, "$day|$c|$w|$score");
        bot('sendMessage', ['chat_id' => $cid, 'text' => $result ? "✅ To‘g‘ri javob!" : "❌ Noto‘g‘ri. To‘g‘ri javob: ".$as[$n]]);
        $n++;
        if ($n < count($qs)) {
            file_put_contents("steps/$cid.txt", "$n|$day");
            bot('sendMessage', ['chat_id' => $cid, 'text' => "Savol ".($n+1).":\n".$qs[$n]]);
        } else {
            unlink("steps/$cid.txt");
            $res = file_get_contents("results/$cid-day.txt");
            list($_, $correct, $wrong, $_score) = explode("|", $res);
            $score = (int)$correct * 10;
            save_score($cid, $name, $score);
            bot('sendMessage', ['chat_id' => $cid, 'text' => save_result($cid, $day, $correct, $wrong), 'parse_mode' => "HTML"]);
        }
    }
}
