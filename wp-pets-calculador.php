<?php
/*
Plugin Name: Calculador de Alimento para Mascotas
Description: Plugin que calcula la cantidad de alimento a comprar (para perros y gatos) según edad, actividad, peso, etc., genera la orden en WooCommerce usando un shortcode, guarda los datos del formulario en la orden (usando un hook adecuado) y muestra los datos en el admin.
Version: 1.4.1
Author: Darwin Cedeño
Author URI: https://darwincd.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Registra el shortcode [food_order_calculator]
 */
function foad_register_shortcode() {
    ob_start();
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

    #close-modal-btn{
        background-color: #6c757d;
        border-color: #6c757d;
    }
    #calculate-btn:hover, #generate-order-btn:hover, #close-modal-btn:hover {
        background: #005177;
    }

    #close-modal-btn:hover{
        background-color: #5a6268;
        border-color: #545b62;
    }

    .ped-sugerido{
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
          <label for="pet_type">Tipo de mascota:</label>
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
          <label>Edad de la mascota:</label>
          <div class="inline-fields">
            <div>
              <input type="number" name="years" id="years" value="0" min="0" placeholder="Años">
            </div>
            <div>
              <!-- Se muestran opciones desde 2 hasta 12 -->
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
          <label for="meals">Veces a comer al día:</label>
          <input type="number" name="meals" id="meals" min="1" placeholder="Ej: 3">
        </p>
        <p>
          <label for="days">Días a comprar comida:</label>
          <input type="number" name="days" id="days" min="1" placeholder="Ej: 15">
        </p>
        <!-- Nuevo selector: Tipo de alimento -->
        <p>
          <label for="food_type">Tipo de alimento:</label>
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
            var years = parseFloat($('#years').val());
            var months = parseFloat($('#months').val());
            
            // Validación: la edad mínima debe ser 2 meses
            if(years === 0 && months < 2){
                alert('La edad de la mascota debe ser al menos 2 meses.');
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
           /* html += '<p><strong>Porcentaje aplicado:</strong> ' + (percentage*100).toFixed(1) + '%</p>';*/
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
                food_type: $('#food_type').val(),
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
    $required_fields = ['suggested_order', 'pet_type', 'pet_name', 'years', 'months', 'weight', 'meals', 'days', 'food_type'];
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

    // Guardar todos los datos del formulario en un array para luego agregarlos a la orden
    $order_data = array(
       'pet_type'  => $pet_type,
       'pet_name'  => $pet_name,
       'years'     => $years,
       'months'    => $months,
       'activity'  => $activity,
       'weight'    => $weight,
       'meals'     => $meals,
       'days'      => $days,
       'food_type' => $food_type,
       'pedido_sugerido' => round($suggested_order,1)  // <-- Campo agregado

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
 * Se utiliza el hook 'woocommerce_checkout_create_order' para garantizar que la información
 * se añada al objeto de la orden cuando se crea.
 */


function foad_add_order_meta($order_id) {
    // 1. Obtener el objeto Order
    $order = wc_get_order($order_id);
    
    // 2. Debug inicial para confirmar ejecución
    error_log('>>>> darwwwxxxxxxxxxFunción foad_add_order_metaxx ejecutándose para el pedidoxxxxxxxxxxxxxxdarwww ' . $order_id . ' <<<<');

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
      'pet_type'  => 'Tipo de mascota',
      'pet_name'  => 'Nombre',
      'years'     => 'Años',
      'months'    => 'Meses',
      'activity'  => 'Nivel de actividad',
      'weight'    => 'Peso',
      'meals'     => 'Veces a comer al día',
      'days'      => 'Días a comprar',
      'food_type' => 'Tipo de alimento',
      'pedido_sugerido'=> 'Pedido sugerido'
    );
    echo '<ul>';

    foreach( $fields as $key => $label ){
        $value = $order->get_meta($key);
        if( $value ){

            if($key == 'pedido_sugerido'){
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . ' Kg</li>';
            }else if($key == 'weight'){
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . ' Kg</li>';
            }
            else{
                echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';

            }
        }
    }
    echo '</ul>';
    echo '</div>';
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'foad_display_order_meta_in_admin', 10, 1 );


