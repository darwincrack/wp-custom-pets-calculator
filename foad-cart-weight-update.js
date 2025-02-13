jQuery(document).ready(function($) {

  // Función para actualizar con un retraso (debounce)
  var delayedUpdate;
  function delayedTotalWeightUpdate() {
      clearTimeout(delayedUpdate);
      delayedUpdate = setTimeout(updateTotalWeight, 1500);
  }




// Usamos MutationObserver para detectar cambios en el contenedor de artículos del carrito.
  var targetNode = document.querySelector('.wc-block-cart-items tbody');
  if ( targetNode ) {
      var observerx = new MutationObserver(function(mutationsList, observer) {

          // Si se eliminó (o agregaron) un nodo, actualizamos el total.
          delayedTotalWeightUpdate();
      });
      var config = { childList: true, subtree: true };
      observerx.observe(targetNode, config);
  }




// Función para observar un input específico
function observarInput(input) {
  // Configura el observer
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === "value") {
        delayedTotalWeightUpdate(); // Llama a la función cuando se detecta un cambio
      }
    });
  });

  // Configuración del observer
  const config = {
    attributes: true, // Observar cambios en los atributos
  };

  // Empieza a observar el input
  observer.observe(input, config);
}

// Función para verificar y observar los inputs
function verificarYObservarInputs() {
  const inputs = document.querySelectorAll("input.wc-block-components-quantity-selector__input"); // Cambia ".miInputClase" por la clase o selector de tus inputs

  // Verificar si existe algún elemento con la clase especificada
  if (inputs.length > 0) {

    // Observa cada input en la lista
    inputs.forEach(input => observarInput(input));

  } else {
    setTimeout(verificarYObservarInputs, 500); // Reintenta después de 1 segundo
  }
}

// Llama a la función verificarYObservarInputs
verificarYObservarInputs();







  function updateTotalWeight() {
      $.ajax({
          url: foad_ajax_obj.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
              action: 'foad_get_cart_total_weight'
          },
          success: function(response) {
              if ( response.success ) {
                  $('.foad-total-weight').html('Kg en tu carrito: ' + response.data.total_weight);
              }
          }
      });
  }
  


  // Actualiza al cargar la página
  updateTotalWeight();


  // También escucha el evento de actualización de fragmentos (WooCommerce)
  $(document.body).on('wc_fragments_refreshed', function(){
      updateTotalWeight();
  });

});
