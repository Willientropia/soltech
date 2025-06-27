<?php
error_reporting(E_ALL); // Reporta todos os erros
ini_set('display_errors', 1); // Exibe os erros no navegador (APENAS PARA DEPURAR)
ini_set('log_errors', 1); // Habilita o log de erros
ini_set('error_log', '/caminho/para/seu/pasta_de_logs/php_errors.log'); // Defina um caminho para o log de erros na Locaweb (se possível)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Contact.php';



$database = new Database();
$db = $database->getConnection();
$contact = new Contact($db);

$data = json_decode(file_get_contents("php://input"));

// Validação básica dos dados
if (
    !empty($data->name) &&
    !empty($data->email) &&
    !empty($data->phone) &&
    filter_var($data->email, FILTER_VALIDATE_EMAIL) // Valida o formato do e-mail
) {
    // Atribui os dados ao objeto de contato
    $contact->name = $data->name;
    $contact->email = $data->email;
    $contact->phone = $data->phone;
    $contact->city = $data->city ?? '';
    $contact->consumption = $data->consumption ?? null;
    $contact->message = $data->message ?? '';
    $contact->source = 'site-form';

    // Tenta criar o contato no banco de dados
    if($contact->create()) {
        // Se o contato foi salvo, tenta enviar o e-mail
        $to = $contact->email;
        $subject = "Recebemos sua solicitação de orçamento - SOL TECH";
        
        // Corpo do E-mail (em HTML para melhor aparência)
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #FFA500;'>Olá, " . htmlspecialchars($contact->name) . "!</h2>
            <p>Recebemos com sucesso a sua solicitação de orçamento.</p>
            <p>Nossa equipe já está analisando seus dados e em breve um de nossos especialistas entrará em contato pelo telefone ou e-mail fornecido.</p>
            <p>Agradecemos o seu interesse na <strong>SOL TECH ENERGIA SOLAR</strong>!</p>
            <br>
            <p>Atenciosamente,</p>
            <p>Equipe SOL TECH</p>
        </body>
        </html>";

        // Headers do e-mail (Importante para Locaweb)
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: <contato@seusite.com.br>' . "\r\n"; // <-- MUITO IMPORTANTE: USE UM E-MAIL VÁLIDO DO SEU DOMÍNIO
        $headers .= 'Reply-To: <contato@seusite.com.br>' . "\r\n";

        // Envia o e-mail
        //mail($to, $subject, $email_body, $headers);

        http_response_code(201); // Created
        echo json_encode(array("message" => "Contato salvo com sucesso e e-mail enviado."));

    } else {
        http_response_code(503); // Service Unavailable
        echo json_encode(array("message" => "Não foi possível salvar o contato no banco de dados."));
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(array("message" => "Dados inválidos ou incompletos. Verifique o formulário."));
}
?>