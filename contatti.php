<?php
/* ==========================================================================
   Ostinati Orizzonti – invio del modulo "Scrivici"
   Riceve il messaggio dal sito e lo inoltra via email all'associazione.
   I messaggi NON vengono salvati sul server.
   ========================================================================== */

// ---- Impostazioni ----
const DESTINATARIO = 'ass.culturaleostinatiorizzonti@gmail.com';
const MITTENTE     = 'info@ostinatiorizzonti.it';      // casella del dominio su TopHost
const NOME_MITTENTE = 'Sito Ostinati Orizzonti';

// Siti da cui il modulo può essere inviato (il sito ufficiale e l'anteprima su GitHub)
const ORIGINI_AMMESSE = [
    'https://www.ostinatiorizzonti.it',
    'https://ostinatiorizzonti.it',
    'https://ostinatiorizzonti.github.io',
];

// Massimo invii per lo stesso visitatore in un'ora (contro gli abusi)
const MAX_INVII_ORA = 5;

// --------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$origine = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origine, ORIGINI_AMMESSE, true)) {
    header('Access-Control-Allow-Origin: ' . $origine);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function rispondi(int $codice, bool $ok, string $messaggio): void
{
    http_response_code($codice);
    echo json_encode(['ok' => $ok, 'messaggio' => $messaggio], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rispondi(405, false, 'Metodo non consentito.');
}

// Richieste da siti non autorizzati
if ($origine !== '' && !in_array($origine, ORIGINI_AMMESSE, true)) {
    rispondi(403, false, 'Origine non autorizzata.');
}

// ---- Trappole per i robot ----
// 1) campo nascosto che le persone non vedono e non compilano
if (!empty($_POST['sito_web'])) {
    rispondi(200, true, 'Grazie! Il tuo messaggio è stato inviato.');
}
// 2) il modulo compilato in meno di 3 secondi è quasi certamente un robot
//    (t = millisecondi passati sulla pagina, misurati dal browser del visitatore)
$trascorso = (int) ($_POST['t'] ?? 0);
if ($trascorso > 0 && $trascorso < 3000) {
    rispondi(200, true, 'Grazie! Il tuo messaggio è stato inviato.');
}

// ---- Lettura e controllo dei campi ----
function pulisci_riga(string $testo): string
{
    // niente a capo né caratteri di controllo nelle intestazioni dell'email
    return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $testo));
}

$nome      = pulisci_riga((string) ($_POST['nome'] ?? ''));
$email     = pulisci_riga((string) ($_POST['email'] ?? ''));
$messaggio = trim(str_replace("\r\n", "\n", (string) ($_POST['messaggio'] ?? '')));
$consenso  = !empty($_POST['consenso']);

if ($nome === '' || mb_strlen($nome) > 100) {
    rispondi(422, false, 'Inserisci il tuo nome.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
    rispondi(422, false, 'Inserisci un indirizzo email valido.');
}
if (mb_strlen($messaggio) < 2) {
    rispondi(422, false, 'Scrivi il tuo messaggio.');
}
if (mb_strlen($messaggio) > 5000) {
    rispondi(422, false, 'Il messaggio è troppo lungo (massimo 5000 caratteri).');
}
if (!$consenso) {
    rispondi(422, false, 'Per inviare il messaggio è necessario accettare l’informativa sulla privacy.');
}

// ---- Limite di invii (l'indirizzo IP è salvato solo come impronta anonima, per un'ora) ----
$cartella = sys_get_temp_dir() . '/oo-contatti';
if (!is_dir($cartella)) {
    @mkdir($cartella, 0700, true);
}
$impronta = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . __FILE__);
$file_limite = $cartella . '/' . $impronta;
$invii = [];
if (is_file($file_limite)) {
    $invii = array_filter(
        array_map('intval', explode(',', (string) @file_get_contents($file_limite))),
        fn($t) => $t > time() - 3600
    );
}
if (count($invii) >= MAX_INVII_ORA) {
    rispondi(429, false, 'Hai inviato troppi messaggi. Riprova tra un po’ o scrivici direttamente a ' . DESTINATARIO . '.');
}
// pulizia dei file più vecchi di un'ora
foreach (glob($cartella . '/*') ?: [] as $f) {
    if (@filemtime($f) < time() - 3600) {
        @unlink($f);
    }
}

// ---- Composizione dell'email ----
$oggetto = 'Nuovo messaggio dal sito – ' . $nome;

$corpo  = "Hai ricevuto un nuovo messaggio dal modulo \"Scrivici\" del sito.\n\n";
$corpo .= "Nome:  $nome\n";
$corpo .= "Email: $email\n";
$corpo .= "Data:  " . date('d/m/Y H:i') . "\n\n";
$corpo .= "Messaggio:\n";
$corpo .= str_repeat('-', 40) . "\n";
$corpo .= $messaggio . "\n";
$corpo .= str_repeat('-', 40) . "\n\n";
$corpo .= "Per rispondere basta premere \"Rispondi\": la risposta andrà direttamente a $email.\n";

mb_internal_encoding('UTF-8');
$intestazioni = [
    'From: ' . mb_encode_mimeheader(NOME_MITTENTE, 'UTF-8', 'B') . ' <' . MITTENTE . '>',
    'Reply-To: ' . mb_encode_mimeheader($nome, 'UTF-8', 'B') . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: Sito Ostinati Orizzonti',
];

$inviato = mail(
    DESTINATARIO,
    mb_encode_mimeheader($oggetto, 'UTF-8', 'B'),
    $corpo,
    implode("\r\n", $intestazioni),
    '-f' . MITTENTE
);

if (!$inviato) {
    rispondi(500, false, 'Non siamo riusciti a inviare il messaggio. Riprova più tardi o scrivici direttamente a ' . DESTINATARIO . '.');
}

$invii[] = time();
@file_put_contents($file_limite, implode(',', $invii), LOCK_EX);

rispondi(200, true, 'Grazie! Il tuo messaggio è stato inviato: ti risponderemo il prima possibile.');
