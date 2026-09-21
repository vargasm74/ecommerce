<?php

class ControladorCarrito{

	/*=============================================
	MOSTRAR TARIFAS
	=============================================*/

	public function ctrMostrarTarifas(){

		$tabla = "comercio";

		$respuesta = ModeloCarrito::mdlMostrarTarifas($tabla);

		return $respuesta;

	}	

	/*=============================================
	CALCULAR RESUMEN SEGURO DE CHECKOUT
	=============================================*/

	static public function ctrCalcularResumenCheckout($ids, $cantidades, $pais = ""){

		if(!is_array($ids) || !is_array($cantidades) || count($ids) === 0 || count($ids) !== count($cantidades)){
			throw new InvalidArgumentException("Carrito invalido");
		}

		if(count($ids) > 50){
			throw new InvalidArgumentException("Demasiados productos en el carrito");
		}

		$pais = strtoupper(trim((string) $pais));

		if($pais !== "" && !preg_match('/^[A-Z]{2}$/', $pais)){
			throw new InvalidArgumentException("Pais invalido");
		}

		$tarifas = ModeloCarrito::mdlMostrarTarifas("comercio");

		if(!$tarifas){
			throw new RuntimeException("No hay configuracion de comercio");
		}

		$subtotalCentavos = 0;
		$pesoTotal = 0.0;
		$requiereEnvio = false;
		$productos = array();

		foreach($ids as $indice => $idProducto){

			$idProducto = filter_var($idProducto, FILTER_VALIDATE_INT, array(
				"options" => array("min_range" => 1)
			));

			$cantidad = filter_var($cantidades[$indice], FILTER_VALIDATE_INT, array(
				"options" => array("min_range" => 1, "max_range" => 100)
			));

			if($idProducto === false || $cantidad === false){
				throw new InvalidArgumentException("Producto o cantidad invalida");
			}

			$producto = ModeloCarrito::mdlObtenerProductoCheckout($idProducto);

			if(!$producto){
				throw new InvalidArgumentException("Producto inexistente");
			}

			$precioBase = (float) $producto["precio"];
			$precioOferta = (float) $producto["precioOferta"];
			$precioReal = $precioOferta > 0 ? $precioOferta : $precioBase;
			$precioCentavos = (int) round($precioReal * 100);
			$subtotalItemCentavos = $precioCentavos * $cantidad;

			$subtotalCentavos += $subtotalItemCentavos;

			$pesoItem = 0.0;

			if($producto["tipo"] === "fisico"){
				$requiereEnvio = true;
				$pesoItem = max(0, (float) $producto["peso"]) * $cantidad;
				$pesoTotal += $pesoItem;
			}

			$productos[] = array(
				"id" => (int) $producto["id"],
				"titulo" => (string) $producto["titulo"],
				"tipo" => (string) $producto["tipo"],
				"cantidad" => $cantidad,
				"precio_unitario" => number_format($precioCentavos / 100, 2, ".", ""),
				"subtotal" => number_format($subtotalItemCentavos / 100, 2, ".", ""),
				"peso_total" => round($pesoItem, 3)
			);
		}

		$impuestoPorcentaje = max(0, (float) $tarifas["impuesto"]);
		$impuestoCentavos = (int) round($subtotalCentavos * ($impuestoPorcentaje / 100));
		$envioCentavos = 0;

		if($requiereEnvio && $pais !== ""){

			$esNacional = strtoupper((string) $tarifas["pais"]) === $pais;
			$tarifaEnvio = (float) ($esNacional ? $tarifas["envioNacional"] : $tarifas["envioInternacional"]);
			$minimoEnvio = (float) ($esNacional ? $tarifas["tasaMinimaNal"] : $tarifas["tasaMinimaInt"]);
			$envioCalculado = $pesoTotal * max(0, $tarifaEnvio);
			$envioReal = max($envioCalculado, max(0, $minimoEnvio));
			$envioCentavos = (int) round($envioReal * 100);
		}

		$totalCentavos = $subtotalCentavos + $impuestoCentavos + $envioCentavos;

		return array(
			"moneda" => "USD",
			"productos" => $productos,
			"subtotal" => number_format($subtotalCentavos / 100, 2, ".", ""),
			"impuesto_porcentaje" => number_format($impuestoPorcentaje, 2, ".", ""),
			"impuesto" => number_format($impuestoCentavos / 100, 2, ".", ""),
			"envio" => number_format($envioCentavos / 100, 2, ".", ""),
			"total" => number_format($totalCentavos / 100, 2, ".", ""),
			"peso_total" => round($pesoTotal, 3),
			"requiere_envio" => $requiereEnvio,
			"pais_envio" => $pais
		);
	}

	/*=============================================
	NUEVAS COMPRAS
	=============================================*/

	static public function ctrNuevasCompras($datos){

		$tabla = "compras";

		$respuesta = ModeloCarrito::mdlNuevasCompras($tabla, $datos);

		if($respuesta == "ok"){

			$tabla = "comentarios";
			ModeloUsuarios::mdlIngresoComentarios($tabla, $datos);

		}

		return $respuesta;

	}
}