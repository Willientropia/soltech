<?php
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

if (
    !empty($data->name) &&
    !empty($data->email) &&
    !empty($data->phone)
) {
    $contact->name = $data->name;
    $contact->email = $data->email;
    $contact->phone = $data->phone;
    $contact->city = $data->city ?? '';
    $contact->consumption = $data->consumption ?? null;
    $contact->message = $data->message ?? '';
    $contact->source = 'site-form';

    if($contact->create()) {
        http_response_code(201);
        echo json_encode(array("message" => "Contato salvo com sucesso."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Não foi possível salvar o contato."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Dados incompletos."));
}
?>