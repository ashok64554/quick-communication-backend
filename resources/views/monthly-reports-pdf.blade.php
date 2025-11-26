<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{env('APP_NAME', 'NewRise Technosys Pvt. Ltd.')}} - {{$data['month'].' '. $data['year']}}</title>
    <style type="text/css">
	    .clearfix:after {
	        content: "";
	        display: table;
	        clear: both;
	    }

	    body {
	        margin: 0 auto;
	        color: #000;
	        background: #FFFFFF;
	        font-family: opensanscondensed;
	        font-size: 14px;
	        background: url("https://ok-go.in/nrt-sms-bg.png");
	        background-position: center center;
	        background-repeat: no-repeat;
	        background-size: cover;
	    }

	    table {
	        width: 100%;
	        border-collapse: collapse;
	        text-align: left;
	        overflow: hidden;
	        border: 1px solid #ecf0f1;
	    }

	    table td,
	    table th {
	        border-top: 1px solid #ecf0f1;
	        padding: 10px;
	    }

	    table th, table td {
	        border-left: 1px solid #ecf0f1;
	        border-right: 1px solid #ecf0f1;
	    }

	    .row {
	    	display: flex;
	    	flex-wrap: wrap;
	    	width: 100%;
	    	float: left;
	    }
	    .col-33 {
	    	width: 33.33%;
	    	float: left;
	    }
	    .col-60 {
	    	width: 60%;
	    	float: left;
	    }
	    .col-40 {
	    	width: 40%;
	    	float: left;
	    }
	    .border {
	    	padding: 10px 10px 10px 0px;
	    	margin: 10px 10px 10px 0px;
	    	border: 1px solid #c9c9c9;
	    }
	    .border-o {
	    	padding: 10px;
	    	margin: 10px;
	    	border: 1px solid #c9c9c9;
	    }
	    .border-r {
	    	padding: 10px 0px 10px 10px;
	    	margin: 10px 0px 10px 10px;
	    	border: 1px solid #c9c9c9;
	    }
	    .text-center {
	    	text-align: center;
	    }
	    .text-right {
	    	text-align: right;
	    }
	    .bm-15 {
	    	padding-bottom: 15px;
	    }
	    .border-1{
	    	border-bottom: 1px solid #c9c9c9;
	    }
	    .p5 {
	    	padding: -10px;
	    }
    </style>
  </head>
  <body>
	<div class="row">
		<div class="col-60"> 
			<a href="javascript:">
				<img src="https://ok-go.in/nrt-sms-logo.png">
			</a>
		</div>
		<div class="col-40 text-right">
			<b>ADDRESS-</b> 463 - A, Pacific Business Center,<br> 
			Behind D-Mart Shopping Center, Hoshangabad Rd,<br> 
			Bhopal, Madhya Pradesh 462026 India<br>
           <b>E-MAIL-</b>info@nrt.co.in
		</div>
	</div>
	
	<div class="row border-1">
		&nbsp;
	</div>
  	
	<h2 class="text-center">
		<center>
			{{$data['month'].' '. $data['year']}}
		</center>
	</h2>

  	<div class="row">
  		<h3 class="text-left">
					SMS Count
			</h3>
	  	<div class="col-33">
	  		<div class="border">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->sms_count_submission==null) ? 0 : number_format_ind($data['overall_total']->sms_count_submission) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Submitted
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-o">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->sms_count_delivered==null) ? 0 : number_format_ind($data['overall_total']->sms_count_delivered) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Delivered
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-r">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->sms_count_failed==null) ? 0 : number_format_ind($data['overall_total']->sms_count_failed) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Failed
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->sms_count_rejected==null) ? 0 : number_format_ind($data['overall_total']->sms_count_rejected) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Rejected
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-o">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->sms_count_invalid==null) ? 0 : number_format_ind($data['overall_total']->sms_count_invalid) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Invalid
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	</div>

	<div class="row">
  		<h3 class="text-left">
					Mobile Number Count
			</h3>
	  	<div class="col-33">
	  		<div class="border">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->mobile_count_submission==null) ? 0 : number_format_ind($data['overall_total']->mobile_count_submission) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Submitted
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-o">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->mobile_count_delivered==null) ? 0 : number_format_ind($data['overall_total']->mobile_count_delivered) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Delivered
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-r">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->mobile_count_failed==null) ? 0 : number_format_ind($data['overall_total']->mobile_count_failed) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Failed
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->mobile_count_rejected==null) ? 0 : number_format_ind($data['overall_total']->mobile_count_rejected) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Rejected
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	  	<div class="col-33">
	  		<div class="border-o">
	  			<div class="text-center">
	  				<h2 class="p5">{{ ($data['overall_total']->mobile_count_invalid==null) ? 0 : number_format_ind($data['overall_total']->mobile_count_invalid) }}</h2>
	  				<strong class="bm-15 text-right">
	  					Total Invalid
	  				</strong>
	  			</div>
	  		</div>
	  	</div>
	</div>
	<br>
	<pagebreak></pagebreak>
    <table>
    	<thead>
    		<tr>
    			<th colspan="7" class="text-center">SMS Count</th>
    		</tr> 
    		<tr>
    			<th class="text-left" width="5%">#</th>
    			<th class="text-left">Group Name</th>
	    		<th class="text-right">Submitted</th>
	    		<th class="text-right">Delivered</th>
	    		<th class="text-right">Failed</th>
	    		<th class="text-right">Rejected</th>
	    		<th class="text-right">Invalid</th>
    		</tr>
    	</thead>
    	<tbody>
    		@forelse($data['data'] as $key => $record)
    		<tr>
    			<td class="text-left">{{ $key+1 }}</td>
    			<td class="text-left">{{ $record->group_name }}</td>
    			<td class="text-right">{{ number_format_ind($record->sms_count_submission) }}</td>
    			<td class="text-right">{{ number_format_ind($record->sms_count_delivered) }}</td>
    			<td class="text-right">{{ number_format_ind($record->sms_count_failed) }}</td>
    			<td class="text-right">{{ number_format_ind($record->sms_count_rejected) }}</td>
    			<td class="text-right">{{ number_format_ind($record->sms_count_invalid) }}</td>
    		</tr>
    		@empty
    		<tr>
    			<td class="text-left" colspan="8">No Record Found....</td>
    		</tr>
    		@endforelse
    	</tbody>
    </table>
	<br>
    <table>
    	<thead>
    		<tr>
    			<th colspan="7" class="text-center">Mobile Number Count</th>
    		</tr> 
    		<tr>
    			<th class="text-left" width="5%">#</th>
    			<th class="text-left">Group Name</th>
	    		<th class="text-right">Submitted</th>
	    		<th class="text-right">Delivered</th>
	    		<th class="text-right">Failed</th>
	    		<th class="text-right">Rejected</th>
	    		<th class="text-right">Invalid</th>
    		</tr>
    	</thead>
    	<tbody>
    		@forelse($data['data'] as $key => $record)
    		<tr>
    			<td class="text-left">{{ $key+1 }}</td>
    			<td class="text-left">{{ $record->group_name }}</td>
    			<td class="text-right">{{ number_format_ind($record->mobile_count_submission) }}</td>
    			<td class="text-right">{{ number_format_ind($record->mobile_count_delivered) }}</td>
    			<td class="text-right">{{ number_format_ind($record->mobile_count_failed) }}</td>
    			<td class="text-right">{{ number_format_ind($record->mobile_count_rejected) }}</td>
    			<td class="text-right">{{ number_format_ind($record->mobile_count_invalid) }}</td>
    		</tr>
    		@empty
    		<tr>
    			<td class="text-left" colspan="8">No Record Found....</td>
    		</tr>
    		@endforelse
    	</tbody>
    </table>
  </body>
</html>