<?php 
/**
 * Reset user's password
 */
add_action('rest_api_init', 'user_forgot_password_api');
function user_forgot_password_api() {
  register_rest_route('auth/v1',
    'forgotpassword/',
    array(
        'methods' => 'POST',
        'callback' => 'user_forgot_password_callback',
    )
  );
}
function user_forgot_password_callback($request) {

  $data = array();

  $email = ($request['email']) ? strtolower(esc_sql($request['email'])) : null;
  $key = ($request['key']) ? esc_sql($request['key']) : null;
  $token = ($request['token']) ? esc_sql($request['token']) : null;
  $action = ($request['action']) ? esc_sql($request['action']) : null;
  $password = ($request['password']) ? esc_sql($request['password']) : null;

  // Send email
  if($action == 'send_link') {

    if(!email_exists($email)) {
      return api_error('Sua conta não foi encontrada! Crie uma conta clicando em "Não tem uma conta?" abaixo.');
    }

    $user = get_user_by('email', $email);
    $key = get_password_reset_key($user);
    $token = get_field('user_token', "user_$user->ID");
    $reset_link = add_query_arg( array( 'key' => $key, 'user' => $token ), WEB_APP_URL . '/login' );

    $from_email = get_field('global_from_email', 'option');
    $site_name = get_bloginfo('name');
    $headers = array(
      "Content-Type: text/html; charset=UTF-8",
      "From: $site_name <$from_email>"
    );
    $message = "Você acabou de solicitar uma redefinição de senha! <a href=\"".$reset_link."\" target=\"_blank\">Clique aqui</a> para prosseguir.
    <br><br>
    Se não conseguir clicar, copie e cole este link no seu navegador: <br><br>$reset_link
    <br><br>" . $site_name;

    if(!is_localhost()) {
      wp_mail($email, "🔓 Redefinição de senha", $message, $headers);
    }

    $data['validation']['status'] = true;
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
  }

  // Allow new password
  elseif ($action == 'allow_update' && $key && $token) {
    $user_id = get_user_by_token($token);
    if(!$user_id) {
      return api_error('Sua conta não foi encontrada! Crie uma conta clicando em "Não tem uma conta?" abaixo.');
    }

    $allow = check_password_reset_key($key, get_user_by('id', $user_id)->user_login);
    if (is_wp_error($allow)) {
      return api_error('Esse link expirou ou já foi usado! Entre na sua conta através das opções abaixo.');
    }

    $data['validation']['status'] = true;
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;   
  }
  
  // Password updated
  elseif ($action == 'new_password' && $key && $token && $password) {
    $user_id = get_user_by_token($token);
    if(!$user_id) {
      return api_error('Essa conta não existe ou não foi ativada através do email que enviamos anteriormente! Busque por "PopLynk".');
    }

    $allow = check_password_reset_key($key, get_user_by('id', $user_id)->user_login);
    if (is_wp_error($allow)) {
      return api_error('Esse link expirou ou já foi usado! Entre na sua conta através das opções abaixo.');
    }

    // Update password...
    wp_set_password( $password, $user_id );

    $data['validation']['status'] = true;
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;   
  }

  return api_error('Esse link expirou ou já foi usado! Entre na sua conta através das opções do menu.');

}