<?php 
/**
 * API to validate user's identification token
 */
add_action('rest_api_init', 'user_token_api');
function user_token_api() {
  register_rest_route('auth/v1',
    'validate/',
    array(
        'methods' => 'POST',
        'callback' => 'user_token_callback',
    )
  );
}
function user_token_callback($request) {

  $data = array();

  $token = ($request['token']) ? esc_sql($request['token']) : null;

  // Empty fields
  if(empty($token)) {
    return api_error('Dados inválidos.');
  }

  // If there's no user with this token
  $user_id = get_user_by_token($token);
  if(!$user_id) {
    return api_error('Você está usando uma conta inválida.');
  }

  // User's blocked
  $user = get_user_by('id', $user_id);
  if(!in_array('pending', $user->roles) && !in_array('member', $user->roles)) {
    return api_error('Algo deu errado! Respondemos dentro de poucas horas no link "Contato" do menu.');
  }

  // User's already a member with a valid email
  if(in_array('member', $user->roles)) {
    return api_error('Este email já foi validado anteriormente! Clique em "Já tem uma conta?" abaixo.');
  }

  // Success
  update_user_ip_details($user_id, $_SERVER['REMOTE_ADDR']);
  update_user_last_activity($user_id);
  $user_role = new WP_User($user_id);
  $user_role->set_role('member');
  $data['validation']['status'] = true;
  $data['validation']['error_message'] = 'Conta validada com sucesso! Clique em "Já tem uma conta?" abaixo para prosseguir com suas metas.';
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}