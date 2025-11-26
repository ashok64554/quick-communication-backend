<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8">
  <title>Send SMS</title>
  <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-beta/css/bootstrap.min.css'>
  <link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css'>
  <style type="text/css">
    body {
      margin: 0;
      background-color: #ecfab6;
    }
  </style>

</head>
<body>
  <div class="container py-3">
    <div class="row">
      <div class="col-md-12"> 
        <div class="row justify-content-center">
          <div class="col-md-10 offset-md-1">
            <!-- form complex example -->
            <form method="post" action="{{route('post-campaign')}}" enctype="multipart/form-data">
              @csrf
              <div class="card card-outline-secondary">
                <div class="card-header">
                  <h3 class="mb-0">Upload file campaign</h3>
                </div>
                <div class="card-body">
                  <div class="row mt-4">
                    <div class="col-sm-12">
                      <label for="file_path">File Upload</label> 
                      <input class="form-control" id="file" name="file" type="file" accept=".csv">
                    </div>

                  </div>
                </div>
                <div class="card-footer">
                  <div class="float-right">
                    <input class="btn btn-primary" type="submit" value="Send">
                  </div>
                </div>
              </div><!--/card-->
            </form>
          </div>
        </div><!--/row-->
        
      </div><!--/col-->
    </div><!--/row-->
  </div><!--/container-->

  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js'></script>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-beta/js/bootstrap.min.js'></script>

</body>
</html>
