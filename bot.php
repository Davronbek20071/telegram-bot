<?php
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

// Get update
$update = json_decode(file_get_contents("php://input"), true);
$message = $update["message"];
$text = $message["text"];
$cid = $message["chat"]["id"];
$mid = $message["message_id"];
$name = $message["from"]["first_name"];

// 1-kunlik Zakovat mashg'uloti
$questions = [
    "00:00–10:00\n1. Bir odam faqat yomg'irli kunlarda mashina minib yuradi. Bugun u piyoda. Nega?\n2. Shifokor singlisini jarrohlik qilolmaydi. Nega?\n3. U doim ochiq turadi, ammo hech kim unga kira olmaydi. Bu nima?",
    
    "10:00–13:00\nSavollarda yashirin ma'no bormi? Qanday so'z kalit rol o'ynadi?",

    "13:00–15:00\nDiqqat testi: 2 - 4 - 8 - 16 - ? - ?", 

    "15:00–30:00\n1. Amir Temur qanday shiorni bayrog'iga yozdirgan?\n2. 3 tarixiy shahar va ularga oid faktlar\n3. Ulug'bek rasadxonasi qayerda?",

    "31:00–46:00\nMini-Zakovat test:\n1. Qur'ondagi eng uzun sura?\n2. Karbon nima?\n3. Termiz qayerda?\n4. Yerda eng ko'p gaz?\n5. “Sherzod” nechta harf?\n6. Qancha qo'shni davlat bor?\n7. “Yosh fiziklar” harakatini kim boshlagan?",

    "46:00–51:00\nNega grafit va olmos bir xil element bo‘lsa-da, xossalari butunlay boshqa?",

    "51:00–56:00\nNega dengiz suvi sho‘r, ammo daryo suvi emas?",

    "56:00–60:00\nO‘zing savol tuz: 4 ta variant bilan, bittasi to‘g‘ri. Misol: U qanday shisha bo‘lib, yorug‘likni yutadi?"
];

$answers = [
    "1. Chunki bugun quyoshli.\n2. Shifokor ayol.\n3. Internet saytning bosh sahifasi.",
    "Masalan, 'faqat yomg'irda' degan ibora odamni chalkashtiradi.",
    "32, 64",
    "1. Adolat bilan kuch.\n2. Samarqand, Buxoro, Termiz.\n3. Samarqandda joylashgan.",
    "1. Baqara\n2. Kimyoviy element (C)\n3. Surxondaryo\n4. Azot\n5. 7 ta\n6. 5 ta\n7. Aniqlash kerak",
    "Sababi: Kristall tuzilmasi boshqacha.",
    "Dengizga tuz to‘planadi, bug‘lanib suv ketadi, ammo tuz qoladi.",
    "To‘g‘ri javob: Yorug‘lik filtri"
];

if ($text == "/start") {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "🤖 Assalomu alaykum, $name!\nZakovat 1-kun mashg‘uloti boshlanmoqda!\nHar bir bo‘limdan so‘ng 'Keyingi' tugmasini bosing.",
        'reply_markup' => json_encode([
            'keyboard' => [[["text" => "1-bo‘limni boshlash"]]],
            'resize_keyboard' => true
        ])
    ]);
} elseif ($text == "1-bo‘limni boshlash") {
    file_put_contents("step_$cid.txt", 0);
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $questions[0],
        'reply_markup' => json_encode([
            'keyboard' => [[["text" => "Javobni ko‘rish"]]],
            'resize_keyboard' => true
        ])
    ]);
} elseif ($text == "Javobni ko‘rish") {
    $step = file_get_contents("step_$cid.txt");
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✅ Javob:\n" . $answers[$step]]);
    bot('sendMessage', ['chat_id' => $cid, 'text' => "✍️ Endi daftarga yoz: xatoni qayerda qilding?"]);
    $step++;
    if ($step < count($questions)) {
        file_put_contents("step_$cid.txt", $step);
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => $questions[$step],
            'reply_markup' => json_encode([
                'keyboard' => [[["text" => "Javobni ko‘rish"]]],
                'resize_keyboard' => true
            ])
        ]);
    } else {
        unlink("step_$cid.txt");
        bot('sendMessage', ['chat_id' => $cid, 'text' => "🎉 Tabriklaymiz! 1-kunlik Zakovat mashg‘uloti tugadi. Ertaga yangi dars uchun tayyor bo‘ling."]);
    }
}
?>
