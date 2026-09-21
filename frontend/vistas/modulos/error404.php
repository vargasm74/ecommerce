<!--=====================================
ERROR 404
======================================-->

<?php
$url = Ruta::ctrRuta();
?>

<div class="container">

	<div class="row">

		<div class="col-xs-12 text-center error404">

			<h1>404</h1>

			<h2>Oops! Página no encontrada</h2>

			<p class="text-muted">La dirección solicitada no existe o fue movida.</p>

			<div style="margin-top:25px">
				<button type="button" class="btn btn-default" onclick="history.back()">
					<i class="fa fa-arrow-left"></i> Volver
				</button>

				<a class="btn btn-default backColor" href="<?php echo Seguridad::e($url); ?>">
					<i class="fa fa-home"></i> Ir al inicio
				</a>
			</div>

		</div>

	</div>

</div>
