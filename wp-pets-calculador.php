<?php
/*
Plugin Name: Calculador de Alimento para Mascotas
Description: Plugin que calcula la cantidad de alimento a comprar (para perros y gatos) según edad, actividad, peso, etc., genera la orden en WooCommerce usando un shortcode, guarda los datos del formulario en la orden (usando un hook adecuado) y muestra los datos en el admin.
Version: 1.4.2
Author: Darwin Cedeño
Author URI: https://darwincd.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}


function foad_register_food_order_cpt() {
    $labels = array(
        'name'                  => _x( 'Pedidos de Alimento', 'Post Type General Name', 'textdomain' ),
        'singular_name'         => _x( 'Pedido de Alimento', 'Post Type Singular Name', 'textdomain' ),
        'menu_name'             => __( 'Pedidos de Alimento', 'textdomain' ),
        'name_admin_bar'        => __( 'Pedido de Alimento', 'textdomain' ),
        'archives'              => __( 'Archivos de Pedidos', 'textdomain' ),
        'attributes'            => __( 'Atributos de Pedido', 'textdomain' ),
        'parent_item_colon'     => __( 'Pedido Padre:', 'textdomain' ),
        'all_items'             => __( 'Todos los Pedidos', 'textdomain' ),
        'add_new_item'          => __( 'Agregar Nuevo Pedido', 'textdomain' ),
        'add_new'               => __( 'Agregar Nuevo', 'textdomain' ),
        'new_item'              => __( 'Nuevo Pedido', 'textdomain' ),
        'edit_item'             => __( 'Editar Pedido', 'textdomain' ),
        'update_item'           => __( 'Actualizar Pedido', 'textdomain' ),
        'view_item'             => __( 'Ver Pedido', 'textdomain' ),
        'view_items'            => __( 'Ver Pedidos', 'textdomain' ),
        'search_items'          => __( 'Buscar Pedido', 'textdomain' ),
        'not_found'             => __( 'No encontrado', 'textdomain' ),
        'not_found_in_trash'    => __( 'No encontrado en la papelera', 'textdomain' ),
        'featured_image'        => __( 'Imagen Destacada', 'textdomain' ),
        'set_featured_image'    => __( 'Establecer imagen destacada', 'textdomain' ),
        'remove_featured_image' => __( 'Remover imagen destacada', 'textdomain' ),
        'use_featured_image'    => __( 'Usar como imagen destacada', 'textdomain' ),
        'insert_into_item'      => __( 'Insertar en el pedido', 'textdomain' ),
        'uploaded_to_this_item' => __( 'Subido a este pedido', 'textdomain' ),
        'items_list'            => __( 'Lista de pedidos', 'textdomain' ),
        'items_list_navigation' => __( 'Navegación de la lista de pedidos', 'textdomain' ),
        'filter_items_list'     => __( 'Filtrar lista de pedidos', 'textdomain' ),
    );
    $args = array(
        'label'                 => __( 'Pedido de Alimento', 'textdomain' ),
        'description'           => __( 'Pedidos realizados con el calculador de alimento', 'textdomain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'author', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_icon'             => 'dashicons-cart',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    register_post_type( 'food_order', $args );
}
add_action( 'init', 'foad_register_food_order_cpt' );





/**
 * Registra el shortcode [food_order_calculator]
 */
function foad_register_shortcode() {
    ob_start();

    // Obtener los códigos postales permitidos para la zona "Querétaro"
    $allowed_postal_codes = array();
    if ( class_exists( 'WC_Shipping_Zones' ) ) {
        $zones = WC_Shipping_Zones::get_zones();

        foreach ( $zones as $zone ) {
            if ( isset( $zone['zone_name'] ) && $zone['zone_name'] === 'Querétaro' ) {
                if ( isset( $zone['zone_locations'] ) && is_array( $zone['zone_locations'] ) ) {
                    foreach ( $zone['zone_locations'] as $location ) {
                        if ( isset( $location->type ) && $location->type === 'postcode' && isset( $location->code ) ) {

                            $allowed_postal_codes[] = $location->code;
                        }
                    }
                }
                break;
            }
        }
    }
    ?>
    <!-- Estilos para un formulario moderno y estilizado -->
    <style>
    #foad-calculator-wrapper {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        border: 1px solid #ddd;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
        border-radius: 8px;
    }
    #foad-calculator-wrapper form p {
        margin-bottom: 15px;
    }
    #foad-calculator-wrapper label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        text-align: left;
    }
    #foad-calculator-wrapper input[type="text"],
    #foad-calculator-wrapper input[type="number"],
    #foad-calculator-wrapper select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .inline-fields {
        display: flex;
        gap: 10px;
    }
    .inline-fields > div {
        flex: 1;
    }
    #calculate-btn, #generate-order-btn, #close-modal-btn {
        background: #0073aa;
        color: #fff;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    #close-modal-btn {
        background-color: #6c757d;
        border-color: #6c757d;
    }
    #calculate-btn:hover, #generate-order-btn:hover, #close-modal-btn:hover {
        background: #005177;
    }
    #close-modal-btn:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }
    .ped-sugerido {
        font-size: 2rem;
    }
    /* Modal */
    #foad-result-modal {
        display: none;
        position: fixed;
        top: 20%;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        padding: 20px;
        border: 1px solid #ccc;
        z-index: 9999;
        width: 90%;
        max-width: 500px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    </style>

    <div id="foad-calculator-wrapper">
      <form id="pet-food-calculator">
        <p>
          <label for="pet_type">Mi compañero es un:</label>
          <select name="pet_type" id="pet_type">
            <option value="perro">Perro</option>
            <option value="gato">Gato</option>
          </select>
        </p>
        <p>
          <label for="pet_name">Nombre:</label>
          <input type="text" name="pet_name" id="pet_name" placeholder="Nombre de la mascota">
        </p>
        <!-- Edad de la mascota: años y meses en una misma línea -->
        <p>
          <label>Su edad es:</label>
          <div class="inline-fields">
            <div>
              <label>Años:</label>
              <input type="number" name="years" id="years" value="0" min="0" placeholder="Años">
            </div>
            <div>
              <label>Meses:</label>
              <select name="months" id="months">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                  <option value="<?php echo $i; ?>"><?php echo $i; ?> mes<?php echo ($i > 1 ? 'es' : ''); ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        </p>
        <!-- Nivel de actividad, solo si la mascota es adulta -->
        <p id="activity-level-container" style="display:none;">
          <label for="activity">Nivel de actividad:</label>
          <select name="activity" id="activity">
            <option value="alto">Alto</option>
            <option value="medio">Medio</option>
            <option value="bajo">Bajo</option>
          </select>
        </p>
        <p>
          <label for="weight">Peso corporal (kg):</label>
          <input type="number" name="weight" id="weight" step="0.1" min="0" placeholder="Ej: 10.5">
        </p>
        <p>
          <label for="meals">Número de veces que come al día:</label>
          <input type="number" name="meals" id="meals" min="1" placeholder="Ej: 3">
        </p>
        <p>
          <label for="days">¿Para cuántos días necesitas comprar?:</label>
          <input type="number" name="days" id="days" min="1" placeholder="Ej: 15">
        </p>
        <!-- Nuevo campo: Código postal -->
        <p>
          <label for="postal_code">Tú código postal:</label>
          <input type="text" name="postal_code" id="postal_code" placeholder="Ingrésalo para corroborar la cobertua en tu zona">
        </p>

        <p>
          <label for="correo_electronico">Tú correo eléctronico:</label>
          <input type="text" name="correo_electronico" id="correo_electronico" placeholder="ej: jhondoe@gmail.com">
        </p>

        <p>
          <label for="telefono">Tú teléfono:</label>
          <input type="text" name="telefono" id="telefono" placeholder="">
        </p>
        <!-- Nuevo selector: Tipo de alimento -->

        <p>
          <label for="food_type">¿Qué tipo de proteína?:</label>
          <select name="food_type" id="food_type">
            <option value="pollo">Pollo</option>
            <option value="res">Res</option>
            <option value="cerdo">Cerdo</option>
            <option value="mixto">Mixto</option>
          </select>
        </p>
        <p>
          <button type="button" id="calculate-btn">Calcular cantidad de alimento</button>
        </p>
      </form>
      
      <!-- Modal para mostrar resultados -->
      <div id="foad-result-modal">
        <div id="foad-results"></div>
        <p style="text-align:center; margin-top:20px;">
            <button type="button" id="close-modal-btn">Cerrar</button>
            <button type="button" id="generate-order-btn">Comprar ahora</button>
        </p>
      </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($){
        // Variable con los códigos postales permitidos (obtenidos de la zona "Querétaro")
        var allowedPostalCodes = <?php echo json_encode($allowed_postal_codes); ?>;
        
        // Mostrar/ocultar el selector de nivel de actividad según el campo de años
        $('#years').on('input', function(){
            var years = parseFloat($(this).val());
            if(years > 0){
                $('#activity-level-container').show();
            } else {
                $('#activity-level-container').hide();
            }
        });
        
        // Al presionar "Calcular cantidad de alimento"
        $('#calculate-btn').on('click', function(){
            // Recoger los valores del formulario
            var petType = $('#pet_type').val();
            var petName = $('#pet_name').val();
            var correo_electronico = $('#correo_electronico').val();
            var telefono = $('#telefono').val();

            var years = parseFloat($('#years').val());
            var months = parseFloat($('#months').val());
            
            // Validación: la edad mínima debe ser 2 meses
            if(years === 0 && months < 2){
                alert('La edad de la mascota debe ser al menos 2 meses.');
                return;
            }


            if(correo_electronico == ""){
                alert('Complete el campo correo eléctronico.');
                return;
            }

            if(telefono == ""){
                alert('Complete el campo teléfono.');
                return;
            }
            
            // Validación del código postal
            var postalCode = $('#postal_code').val().trim();
            if(postalCode === ''){
               alert('Por favor, ingresa tu código postal.');
               return;
            }
            if( allowedPostalCodes.length > 0 && $.inArray(postalCode, allowedPostalCodes) === -1 ){
               alert('Lo sentimos, para el código postal ingresado aún no se hacen envíos.');
               return;
            }
            
            var totalMonths = years * 12 + months;
            var isAdult = totalMonths > 12; // Adulto si es mayor a 12 meses
            var activity = $('#activity').val();
            var weight = parseFloat($('#weight').val());
            var meals = parseFloat($('#meals').val());
            var days = parseFloat($('#days').val());
            var foodType = $('#food_type').val();
            
            if(isNaN(weight) || isNaN(meals) || isNaN(days) || weight <= 0 || meals <= 0 || days <= 0){
                alert('Por favor, complete todos los campos numéricos con valores válidos.');
                return;
            }
            
            var percentage = 0;
            if(!isAdult){
                // Cachorro: aplicar la tabla de porcentajes según la edad en meses.
                if(totalMonths >= 2 && totalMonths <= 4){
                    percentage = 0.10;
                } else if(totalMonths >= 5 && totalMonths <= 6){
                    percentage = 0.08;
                } else if(totalMonths >= 7 && totalMonths <= 8){
                    percentage = 0.07;
                } else if(totalMonths >= 9 && totalMonths <= 10){
                    percentage = 0.06;
                } else if(totalMonths >= 11 && totalMonths <= 12){
                    percentage = 0.05;
                } else {
                    percentage = 0.10; // Valor por defecto para edades muy bajas
                }
            } else {
                // Adulto: usar porcentaje según nivel de actividad (valores de ejemplo)
                if(activity === 'alto'){
                    percentage = 0.04; // 4%
                } else if(activity === 'medio'){
                    percentage = 0.03; // 3%
                } else if(activity === 'bajo'){
                    percentage = 0.02; // 2%
                }
            }
            
            var dailyAmount = weight * percentage; // kg de alimento por día
            var portionAmount = dailyAmount / meals; // kg por porción
            var suggestedOrder = dailyAmount * days; // kg totales sugeridos
            
            // Construir el HTML con los resultados, redondeando el pedido sugerido a 1 dígito decimal
            var html = '<h3>Resultados</h3>';
            html += '<p><strong>Edad en meses:</strong> ' + totalMonths.toFixed(0) + ' meses (' + (isAdult ? 'Adulto' : 'Cachorro') + ')</p>';
            html += '<p><strong>Cantidad de alimento por día:</strong> ' + dailyAmount.toFixed(2) + ' kg</p>';
            html += '<p><strong>Cantidad por porción:</strong> ' + portionAmount.toFixed(2) + ' kg</p>';
            html += '<p class="ped-sugerido"><strong>Pedido sugerido:</strong> ' + suggestedOrder.toFixed(1) + ' kg</p>';
            $('#foad-results').html(html);
            
            // Guardar el valor de pedido sugerido en el botón para usarlo al generar la orden
            $('#generate-order-btn').data('suggestedOrder', suggestedOrder);
            
            // Mostrar la ventana modal
            $('#foad-result-modal').fadeIn();
        });
        
        // Botón para cerrar la ventana modal
        $('#close-modal-btn').on('click', function(){
            $('#foad-result-modal').fadeOut();
        });
        
        // Al hacer click en "Generar Orden", enviar toda la información vía AJAX
        $('#generate-order-btn').on('click', function(){
            var suggestedOrder = $(this).data('suggestedOrder');
            // Recoger todos los datos del formulario
            var dataForm = {
                pet_type: $('#pet_type').val(),
                pet_name: $('#pet_name').val(),
                years: $('#years').val(),
                months: $('#months').val(),
                activity: $('#activity').val(),
                weight: $('#weight').val(),
                meals: $('#meals').val(),
                days: $('#days').val(),
                postal_code: $('#postal_code').val(),
                food_type: $('#food_type').val(),
                correo_electronico : $('#correo_electronico').val(),
                telefono : $('#telefono').val(),
                suggested_order: suggestedOrder,
                action: 'generate_order'
            };
            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                dataType: 'json',
                data: dataForm,
                success: function(response){
                    if(response.success){
                        // Redirige al usuario al carrito de WooCommerce
                        window.location.href = response.data.redirect;
                    } else {
                        alert(response.data ? response.data : 'Error al generar la orden.');
                    }
                },
                error: function(){
                    alert('Error en la comunicación con el servidor.');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('food_order_calculator', 'foad_register_shortcode');

/**
 * Handler AJAX para generar la orden en WooCommerce y guardar la información del formulario en la sesión.
 */
function foad_generate_order() {
    // Validar y recoger los datos enviados vía AJAX
    $required_fields = ['suggested_order', 'pet_type', 'pet_name', 'years', 'months', 'weight', 'meals', 'days', 'food_type', 'postal_code','correo_electronico','telefono'];
    foreach ($required_fields as $field) {
        if ( ! isset( $_POST[$field] ) ) {
            wp_send_json_error('Falta el campo ' . $field);
        }
    }
    $suggested_order = floatval( $_POST['suggested_order'] );
    $pet_type      = sanitize_text_field( $_POST['pet_type'] );
    $pet_name      = sanitize_text_field( $_POST['pet_name'] );
    $years         = intval( $_POST['years'] );
    $months        = intval( $_POST['months'] );
    $activity      = isset($_POST['activity']) ? sanitize_text_field( $_POST['activity'] ) : '';
    $weight        = floatval( $_POST['weight'] );
    $meals         = intval( $_POST['meals'] );
    $days          = intval( $_POST['days'] );
    $food_type     = sanitize_text_field( $_POST['food_type'] );
    $postal_code = sanitize_text_field( $_POST['postal_code'] );
    $correo_electronico   = sanitize_text_field( $_POST['correo_electronico'] );
    $telefono   = sanitize_text_field( $_POST['telefono'] );

    // Guardar todos los datos del formulario en un array para luego agregarlos a la orden
    $order_data = array(
       'pet_type'        => $pet_type,
       'pet_name'        => $pet_name,
       'years'           => $years,
       'months'          => $months,
       'activity'        => $activity,
       'weight'          => $weight,
       'meals'           => $meals,
       'days'            => $days,
       'food_type'       => $food_type,
       'codigo_postal'   => $postal_code,
       'correo_electronico'   => $correo_electronico,
       'telefono'   => $telefono,
       'pedido_sugerido' => round($suggested_order,1)
    );
    // Almacenar en la sesión de WooCommerce para luego agregar a la orden
    if ( class_exists('WC_Session') && WC()->session ) {
        WC()->session->set('foad_order_data', $order_data);
    }

    // Determinar el producto según el tipo de alimento y el tipo de mascota
    $product_id = 0;
    if($food_type === 'mixto'){
        // Validar que el pedido sugerido sea mayor a 1.5 kg para el mixto
        if($suggested_order <= 1.5){
            wp_send_json_error('El pedido mixto solo está disponible cuando el pedido sugerido es mayor a 1.5 kg.');
        }
        if($pet_type === 'perro'){
            $product_id = 226;
        } elseif($pet_type === 'gato'){
            $product_id = 220;
        }
        // Calcular la cantidad de unidades: cada 500 g equivale a una unidad
        $quantity_units = ceil($suggested_order / 0.5);
        // Vaciar el carrito previamente
        if ( WC()->cart ) {
            WC()->cart->empty_cart();
        }
        // Obtener la única variación (500 g)
        $variation_id = foad_get_variation_id_by_attribute( $product_id, 'pa_gramaje', '500 g' );
        // Agregar el producto mixto
        WC()->cart->add_to_cart( $product_id, $quantity_units, $variation_id, array( 'pa_gramaje' => '500 g' ) );
    } else {
        // Para los demás tipos de alimento
        if($pet_type === 'perro'){
            if($food_type === 'pollo'){
                $product_id = 69;
            } elseif($food_type === 'res'){
                $product_id = 71;
            } elseif($food_type === 'cerdo'){
                $product_id = 63;
            }
        } elseif($pet_type === 'gato'){
            if($food_type === 'pollo'){
                $product_id = 73;
            } elseif($food_type === 'res'){
                $product_id = 74;
            } elseif($food_type === 'cerdo'){
                $product_id = 72;
            }
        }
        if( ! $product_id ){
            wp_send_json_error('Producto no determinado.');
        }
        
        // Vaciar el carrito previamente
        if ( WC()->cart ) {
            WC()->cart->empty_cart();
        }
        
        // Calcular la parte entera y el remanente para asignar las bolsas
        $full = floor( $suggested_order );
        $remainder = $suggested_order - $full;
        
        $extra_250 = 0;
        $extra_500 = 0;
        
        if( $remainder > 0 ) {
            if( $remainder <= 0.25 ) {
                $extra_250 = 1;
            } elseif( $remainder <= 0.5 ) {
                $extra_500 = 1;
            } elseif( $remainder <= 0.75 ) {
                $extra_250 = 1;
                $extra_500 = 1;
            } else {
                $extra_500 = 2;
            }
        }
        
        // Obtener los ID de variación según el atributo “pa_gramaje”
        $variation_id_1kg = foad_get_variation_id_by_attribute( $product_id, 'pa_gramaje', '1 kg' );
        $variation_id_500 = foad_get_variation_id_by_attribute( $product_id, 'pa_gramaje', '500 g' );
        $variation_id_250 = foad_get_variation_id_by_attribute( $product_id, 'pa_gramaje', '250 g' );
        
        // Agregar bolsas de 1 kg (por la parte entera)
        for( $i = 0; $i < $full; $i++ ){
            WC()->cart->add_to_cart( $product_id, 1, $variation_id_1kg, array( 'pa_gramaje' => '1 kg' ) );
        }
        // Agregar bolsas de 500 g
        for( $i = 0; $i < $extra_500; $i++ ){
            WC()->cart->add_to_cart( $product_id, 1, $variation_id_500, array( 'pa_gramaje' => '500 g' ) );
        }
        // Agregar bolsas de 250 g
        for( $i = 0; $i < $extra_250; $i++ ){
            WC()->cart->add_to_cart( $product_id, 1, $variation_id_250, array( 'pa_gramaje' => '250 g' ) );
        }
    }



// --- Aquí se crea el Custom Post Type para el pedido ---
// Crear un título para el pedido (por ejemplo, usando el nombre de la mascota y la fecha)
$post_title = sprintf( 'Pedido de %s - %s', $pet_name, date_i18n( 'd/m/Y H:i' ) );

// Construir el contenido del post (opcional, puedes darle formato o incluir más datos)
$post_content = "Información del Pedido:\n\n";
foreach ( $order_data as $key => $value ) {
    $post_content .= sprintf( "%s: %s\n", ucfirst( str_replace('_', ' ', $key) ), $value );
}

$post_data = array(
    'post_title'   => $post_title,
    'post_content' => $post_content,
    'post_status'  => 'publish', // O 'pending' si deseas aprobarlos manualmente
    'post_type'    => 'food_order',
    'post_author'  => get_current_user_id(), // Guarda el ID del usuario, si está logueado
);

$post_id = wp_insert_post( $post_data );

if ( ! is_wp_error( $post_id ) ) {
    // Guardar los mismos metadatos en el CPT para una consulta más sencilla en el futuro
    foreach ( $order_data as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }
}


    
    $cart_url = wc_get_cart_url();
    wp_send_json_success( array( 'redirect' => $cart_url ) );
    wp_die();
}
add_action('wp_ajax_generate_order', 'foad_generate_order');
add_action('wp_ajax_nopriv_generate_order', 'foad_generate_order');

/**
 * Función auxiliar para obtener el ID de variación dado un producto variable, el nombre del atributo y el valor.
 * Se asume que el atributo se llama "pa_gramaje".
 */
function foad_get_variation_id_by_attribute( $product_id, $attribute, $value ) {
    $product = wc_get_product( $product_id );
    if( ! $product || ! $product->is_type( 'variable' ) ) {
        return 0;
    }
    $available_variations = $product->get_available_variations();
    foreach( $available_variations as $variation ) {
        $attr_key = 'attribute_' . $attribute;
        if( isset( $variation['attributes'][ $attr_key ] ) && $variation['attributes'][ $attr_key ] === $value ) {
            return $variation['variation_id'];
        }
    }
    return 0;
}

/**
 * Guardar los datos del formulario (almacenados en la sesión) como metadatos en la orden.
 * Se utiliza el hook 'woocommerce_new_order' para garantizar que la información
 * se añada al objeto de la orden cuando se crea.
 */
function foad_add_order_meta($order_id) {
    // 1. Obtener el objeto Order
    $order = wc_get_order($order_id);
    
    // 2. Debug inicial para confirmar ejecución
    error_log('>>>> foad_add_order_meta ejecutándose para el pedido ' . $order_id . ' <<<<');

    // 3. Verificar si la sesión está activa y tiene datos
    if (WC()->session && WC()->session->get('foad_order_data')) {
        $order_data = WC()->session->get('foad_order_data');
        
        error_log('Datos de sesión encontrados: ' . print_r($order_data, true));

        // 4. Recorrer y añadir metadatos
        foreach ($order_data as $key => $value) {
            $order->update_meta_data($key, $value);
            error_log("Añadido meta: $key => " . print_r($value, true));
        }

        // 5. Guardar cambios y limpiar sesión
        $order->save(); // ¡Importante para persistir los cambios!
        WC()->session->__unset('foad_order_data');
        
        error_log('Metadatos guardados y sesión limpiada');
    } else {
        error_log('No se encontraron datos en la sesión o la sesión no está activa');
    }
}
add_action('woocommerce_new_order', 'foad_add_order_meta', 20, 1);

/**
 * Mostrar los datos guardados en el admin de WooCommerce (en la pantalla de edición de la orden)
 */
function foad_display_order_meta_in_admin( $order ){
    echo '<div class="order_data_column">';
    echo '<h4>Datos de la mascota</h4>';
    $fields = array(
      'pet_type'                => 'Tipo de mascota',
      'pet_name'                => 'Nombre',
      'years'                   => 'Años',
      'months'                  => 'Meses',
      'activity'                => 'Nivel de actividad',
      'weight'                  => 'Peso',
      'meals'                   => 'Veces a comer al día',
      'days'                    => 'Días a comprar',
      'codigo_postal'           => 'Código postal',
      'correo_electronico'      => 'Correo eléctronico',
      'telefono'                => 'Teléfono',
      'food_type'               => 'Tipo de alimento',
      'pedido_sugerido'         => 'Pedido sugerido'
    );
    echo '<ul>';

    foreach( $fields as $key => $label ){
        $value = $order->get_meta($key);
        if( $value ){
            if($key == 'pedido_sugerido'){
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . ' Kg</li>';
            } else if($key == 'weight'){
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . ' Kg</li>';
            } else{
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';
            }
        }
    }
    echo '</ul>';
    echo '</div>';
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'foad_display_order_meta_in_admin', 10, 1 );


/**
 * -------------------------------------------------------------
 * Agregar en el carrito el total de kilogramos que el cliente tiene.
 * Se suma el peso (en kg) de cada artículo, usando el atributo "pa_gramaje"
 * o el peso del producto si no se encuentra el atributo.
 * -------------------------------------------------------------
 */



/**
 * Inyecta hooks en los bloques del carrito (WooCommerce Blocks)
 * Basado en la solución del Visual Hook Guide de Business Bloomer.
 */
add_filter( 'render_block', 'bbloomer_woocommerce_cart_block_do_actions', 9999, 2 );
function bbloomer_woocommerce_cart_block_do_actions( $block_content, $block ) {
    $blocks = array(
        'woocommerce/cart',
        'woocommerce/filled-cart-block',
        'woocommerce/cart-items-block',
        'woocommerce/cart-line-items-block',
        'woocommerce/cart-cross-sells-block',
        'woocommerce/cart-cross-sells-products-block',
        'woocommerce/cart-totals-block',
        'woocommerce/cart-order-summary-block',
        'woocommerce/cart-order-summary-heading-block',
        'woocommerce/cart-order-summary-coupon-form-block',
        'woocommerce/cart-order-summary-subtotal-block',
        'woocommerce/cart-order-summary-fee-block',
        'woocommerce/cart-order-summary-discount-block',
        'woocommerce/cart-order-summary-shipping-block',
        'woocommerce/cart-order-summary-taxes-block',
        'woocommerce/cart-express-payment-block',
        'woocommerce/proceed-to-checkout-block',
        'woocommerce/cart-accepted-payment-methods-block',
    );
    if ( in_array( $block['blockName'], $blocks ) ) {
        ob_start();
        do_action( 'bbloomer_before_' . $block['blockName'] );
        echo $block_content;
        do_action( 'bbloomer_after_' . $block['blockName'] );
        $block_content = ob_get_clean();
    }
    return $block_content;
}

/**
 * Convierte un string de peso (ej. "1 kg", "500 g") a kilogramos (float)
 */
function foad_convert_weight_to_kg( $weight_string ) {
    $weight_string = strtolower( $weight_string );
    if ( strpos( $weight_string, 'kg' ) !== false ) {
        $value = floatval( str_replace( 'kg', '', $weight_string ) );
        return $value;
    } elseif ( strpos( $weight_string, 'g' ) !== false ) {
        $value = floatval( str_replace( 'g', '', $weight_string ) );
        return $value / 1000;
    }
    return 0;
}

/**
 * Calcula y muestra el total de kilogramos en el carrito.
 * Se engancha después del bloque de totales (cart-totals-block) de WooCommerce Blocks.
 */
function foad_display_total_weight_in_cart() {
    if ( ! WC()->cart ) return;
    $total_weight = 0;
    foreach ( WC()->cart->get_cart() as $cart_item ) {

        $weight_text = '';

        // Si el producto tiene el atributo de variación "pa_gramaje", se usa ese valor.
        if ( isset( $cart_item['variation']['pa_gramaje'] ) && ! empty( $cart_item['variation']['pa_gramaje'] ) ) {

            $weight_text = $cart_item['variation']['pa_gramaje'];
        }
        if ( ! empty( $weight_text ) ) {
            $item_weight = foad_convert_weight_to_kg( $weight_text );
        } else {

            // Sino, se utiliza el peso asignado al producto (asumido en kg)
            $product = $cart_item['data'];
            $item_weight = floatval( $product->get_weight() );
        }
        $total_weight += $item_weight * $cart_item['quantity'];
    }

    WC()->session->set('total_weight', $total_weight);

    // Mostrar el total de kilogramos. Puedes personalizar el HTML y estilos.
    echo '<div class="foad-total-weight" style="margin-top:10px;font-weight:bold;">';
    echo esc_html__( 'Kg en tu carrito: ', 'text-domain' ) .  $total_weight. ' kg';
    echo '</div>';
}
add_action( 'bbloomer_before_woocommerce/proceed-to-checkout-block', 'foad_display_total_weight_in_cart' );


/**
 * Endpoint AJAX para obtener el total de kilogramos del carrito.
 */
function foad_get_cart_total_weight_ajax() {


    if ( ! WC()->cart ) {
        wp_send_json_error('No hay carrito');
    }
    $total_weight = 0;
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $weight_text = '';
        if ( isset( $cart_item['variation']['pa_gramaje'] ) && ! empty( $cart_item['variation']['pa_gramaje'] ) ) {
            $weight_text = $cart_item['variation']['pa_gramaje'];
        }
        if ( ! empty( $weight_text ) ) {
            $item_weight = foad_convert_weight_to_kg( $weight_text );
        } else {
            $product = $cart_item['data'];
            $item_weight = floatval( $product->get_weight() );
        }
        $total_weight += $item_weight * $cart_item['quantity'];
    }
    WC()->session->set('total_weight', wc_format_decimal($total_weight,2));
        
    wp_send_json_success( array( 'total_weight' => wc_format_decimal( $total_weight, 2 ) . ' kg' ) );


}
add_action( 'wp_ajax_foad_get_cart_total_weight', 'foad_get_cart_total_weight_ajax' );
add_action( 'wp_ajax_nopriv_foad_get_cart_total_weight', 'foad_get_cart_total_weight_ajax' );

/**
 * Encola el script para actualizar dinámicamente el total de kilogramos en el carrito.
 */
function foad_enqueue_cart_weight_update_script() {
    if ( is_cart() ) {
        wp_enqueue_script( 'foad-cart-weight-update', plugin_dir_url( __FILE__ ) . 'foad-cart-weight-update.js', array('jquery'), '8.0', true );
        wp_localize_script( 'foad-cart-weight-update', 'foad_ajax_obj', array(
            'ajax_url' => admin_url( 'admin-ajax.php' )
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'foad_enqueue_cart_weight_update_script' );



// En el archivo functions.php de tu tema o en un plugin
add_filter('woocommerce_attribute_label', 'cambiar_etiqueta_gramaje', 10, 3);

function cambiar_etiqueta_gramaje($label, $name, $product) {
    if($name === 'pa_gramaje') {
        return 'Contenido Neto'; // Nuevo nombre que quieres mostrar
    }
    return $label;
}


/**
 * Agregar columnas personalizadas en el listado de "Pedidos de Alimento" en el admin
 */
function foad_set_custom_food_order_columns($columns) {
    // Opcional: quitar la columna de fecha para reordenarla después
    unset($columns['date']);
    $columns['pet_type']           = __('Tipo de Mascota', 'textdomain');
    $columns['pet_name']           = __('Nombre', 'textdomain');
    $columns['years']              = __('Años', 'textdomain');
    $columns['months']             = __('Meses', 'textdomain');
    $columns['activity']           = __('Nivel de Actividad', 'textdomain');
    $columns['weight']             = __('Peso', 'textdomain');
    $columns['meals']              = __('Veces al Día', 'textdomain');
    $columns['days']               = __('Días a Comprar', 'textdomain');
    $columns['codigo_postal']      = __('Código Postal', 'textdomain');
    $columns['correo_electronico'] = __('Correo Electrónico', 'textdomain');
    $columns['telefono']           = __('Teléfono', 'textdomain');
    $columns['food_type']          = __('Tipo de Alimento', 'textdomain');
    $columns['pedido_sugerido']    = __('Pedido Sugerido', 'textdomain');
    // Reagregar la columna de fecha al final
    $columns['date']               = __('Fecha', 'textdomain');
    return $columns;
}
add_filter('manage_food_order_posts_columns', 'foad_set_custom_food_order_columns');



/**
 * Mostrar el contenido de las columnas personalizadas en el listado de pedidos
 */
function foad_custom_food_order_column( $column, $post_id ) {
    switch ( $column ) {
        case 'pet_type':
            echo esc_html( get_post_meta( $post_id, 'pet_type', true ) );
            break;
        case 'pet_name':
            echo esc_html( get_post_meta( $post_id, 'pet_name', true ) );
            break;
        case 'years':
            echo esc_html( get_post_meta( $post_id, 'years', true ) );
            break;
        case 'months':
            echo esc_html( get_post_meta( $post_id, 'months', true ) );
            break;
        case 'activity':
            echo esc_html( get_post_meta( $post_id, 'activity', true ) );
            break;
        case 'weight':
            echo esc_html( get_post_meta( $post_id, 'weight', true ) ) . ' kg';
            break;
        case 'meals':
            echo esc_html( get_post_meta( $post_id, 'meals', true ) );
            break;
        case 'days':
            echo esc_html( get_post_meta( $post_id, 'days', true ) );
            break;
        case 'codigo_postal':
            echo esc_html( get_post_meta( $post_id, 'codigo_postal', true ) );
            break;
        case 'correo_electronico':
            echo esc_html( get_post_meta( $post_id, 'correo_electronico', true ) );
            break;
        case 'telefono':
            echo esc_html( get_post_meta( $post_id, 'telefono', true ) );
            break;
        case 'food_type':
            echo esc_html( get_post_meta( $post_id, 'food_type', true ) );
            break;
        case 'pedido_sugerido':
            echo esc_html( get_post_meta( $post_id, 'pedido_sugerido', true ) ) . ' kg';
            break;
    }
}
add_action('manage_food_order_posts_custom_column', 'foad_custom_food_order_column', 10, 2);



function foad_remove_row_actions( $actions, $post ) {
    if ( 'food_order' === get_post_type( $post ) ) {
        // Se eliminan las acciones de edición, edición rápida y papelera.
        unset( $actions['edit'] );
        unset( $actions['inline hide-if-no-js'] );
        // Opcional: si también deseas quitar la opción de “Ver”, descomenta la siguiente línea:
         unset( $actions['view'] );
    }
    return $actions;
}
add_filter( 'post_row_actions', 'foad_remove_row_actions', 10, 2 );


function foad_remove_food_order_submenu() {
    remove_submenu_page( 'edit.php?post_type=food_order', 'post-new.php?post_type=food_order' );
}
add_action( 'admin_menu', 'foad_remove_food_order_submenu' );


function foad_disable_food_order_editing() {
    global $pagenow;
    // Si se intenta acceder a la pantalla de edición o creación de un pedido
    if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) && isset($_GET['post_type']) && 'food_order' === $_GET['post_type'] ) {
        wp_redirect( admin_url('edit.php?post_type=food_order') );
        exit;
    }
    // También se redirige si se está editando un pedido existente
    if ( 'post.php' === $pagenow && isset($_GET['post']) ) {
        $post = get_post( intval( $_GET['post'] ) );
        if ( $post && 'food_order' === $post->post_type ) {
            wp_redirect( admin_url('edit.php?post_type=food_order') );
            exit;
        }
    }
}
add_action( 'admin_init', 'foad_disable_food_order_editing' );