 <!DOCTYPE html>

 <html>
 <link href="https://fonts.googleapis.com/css? family=Open+Sans:300, 400, 700" rel="stylesheet" type="text/css"/>

<style>
	@charset "UTf-8";
	@import url(https://fonts.googleapis.com/css?font-family="Open+Sans:300, 400, 700");

	body{
		font-family: "Open Sans", sans-serif;
		font-weight: 300;
		line-height: 1.42em;
		color: #A7A1AE;
		background: #1F2739;

	}
	h1{
		font-size: 3em;
		font-weight: 300;
		text-align: center;
		display: block;
		line-height: 1em;
		color: #FB667A;

	}
	h2 a{
		font-weight: 700;
		text-transform: uppercase;
		color: #FB667A;
		text-decoration: none;

	}
	.blue{color: #185875;}
	.yellow{color: #FfF842;}

	.container th h1{
		font-weight: bold;
		font-size: 1em;
		text-align: left;
		color: #185875;

	}
	.container tr{
		font-weight: normal;
		font-size: 1em;
		-webkit-box-shadow:0 2px 2px-2px #0E1119;
		-moz-box-shadow:0 2px 2px -2px #0E1119;
		box-shadow: 0 2px 2px -2px #0E1119;
		cursor: pointer;
	}
	.container{
		text-align: left;
		overflow: hidden;
		width: 80%;
		margin: 0 auto;
		display: table;
		padding: 0 0 8em 0;

	}
	.container td, .container th{
		padding-bottom: 2%;
		padding-top: 2%;
		padding-left: 2%;

	}
	/*Background-color of the odd rows */
	.container tr:nth-child(odd){
		background-color: #323C50;

	}
	/*Background-color of the even rows*/
	.container tr:nth-child(even){
		background-color: #2C3446;
	}
	.container th{
		background-color: #1F2739;

	}
	.container td:first-child{color: #FB667A;}

	.container tr:hover{
		background-color: #464A52;
		-webkit-box-shadow:0 6px 6px -6px #0E1119;
		-moz-box-shadow:0 6px 6px -6px #0E1119;
		box-shadow: 0 6px 6px -6px #0E1119;
	}

	.container tr:nth-child(1)
	{
		background-color: #FFF842;
		color: #403E10;
		font-weight: bold;
		box-shadow: #7F7C21 -1px  1px, #7F7C21 -2px 2px, #7F7C21 -3px 3px, #7F7C21 -4px 4px, #7F7C21 -5px 5px, #7F7C21 -6px 6px;
		transform: translate3d(6px, -6px, 0);
		transition-delay: 0s;
		transition-duration: 0.4s;
		transition-property: all;
		transition-timing-function: line;
	}

	.container tr:hover{
		background-color: #FFF842;
		color: #403E10;
		font-weight: bold;
		box-shadow: #7F7C21 -1px  1px, #7F7C21 -2px 2px, #7F7C21 -3px 3px, #7F7C21 -4px 4px, #7F7C21 -5px 5px, #7F7C21 -6px 6px;
		transform: translate3d(6px, -6px, 0);
		transition-delay: 0s;
		transition-duration: 0.2s;
		transition-property: all;
		transition-timing-function: line;
	}
</style>


 <h1><span class="yellow">DLR Codes</span></h1>

 	 <table class="container">
 	 	<tbody>
 	 		<tr>
 	 			<td>Code</td>
 	 			<td>Description</td>
 	 		</tr>
 	 		@foreach($codes as $key => $code)
 	 		<tr>
 	 			<td>{{$code->dlr_code}}</td>
 	 			<td>{{$code->description}}</td>
 	 			{{-- <td>{{($code->is_refund_applicable==1) ? 'Yes': 'No'}}</td>
 	 			<td>{{($code->is_retry_applicable==1) ? 'Yes': 'No'}}</td> --}}
 	 		</tr>
 	 		@endforeach
 	 	</tbody>
 	 </table>

 	 	</tbody>
 	 	</html>