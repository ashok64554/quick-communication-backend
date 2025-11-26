<!doctype html>

<html
  lang="en"
  class="light-style layout-compact layout-menu-fixed layout-navbar-hidden"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>{{env('APP_NAME')}}</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('favicon.ico')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendor/fonts/fontawesome.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/tabler-icons.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/flag-icons.css" />

    <link rel="stylesheet" href="assets/vendor/css/rtl/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/rtl/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />

    <link rel="stylesheet" href="assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
    <style type="text/css">
      .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
        margin: 1rem !important;
      }
    </style>
  </head>

  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">

        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand">
            <a href="index.html" class="app-brand-link">
              <span class="app-brand-logo">
                <img class="img-fluid" src="https://app.ok-go.in/static/media/nrtsms.625976b2.png" style="width: 125px;">
              </span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
              <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <!-- Apps & Pages -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text" data-i18n="Application Logs">Application Logs</span>
            </li>
            @foreach($files as $file)
            <li class="menu-item @if ($current_file == $file) active @endif">
              <a href="?l={{ \Illuminate\Support\Facades\Crypt::encrypt($file) }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-file"></i>
                <div data-i18n="{{$file}}">{{$file}}</div>
              </a>
            </li>
            @endforeach
          </ul>
        </aside>

        <div class="layout-page">

          <div class="content-wrapper">

            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="py-3 mb-2"><span class="text-muted fw-light">
                @if($current_file)
                  <a href="?dl={{ \Illuminate\Support\Facades\Crypt::encrypt($current_file) }}{{ ($current_folder) ? '&f=' . \Illuminate\Support\Facades\Crypt::encrypt($current_folder) : '' }}" class="btn btn-sm btn-success">
                    <span class="fa fa-download"></span>&nbsp;&nbsp;Download file
                  </a>
                  <a id="clean-log" href="?clean={{ \Illuminate\Support\Facades\Crypt::encrypt($current_file) }}{{ ($current_folder) ? '&f=' . \Illuminate\Support\Facades\Crypt::encrypt($current_folder) : '' }}" class="btn btn-sm btn-warning">
                    <span class="fa fa-sync"></span>&nbsp;&nbsp;Clean file
                  </a>
                  <a id="delete-log" href="?del={{ \Illuminate\Support\Facades\Crypt::encrypt($current_file) }}{{ ($current_folder) ? '&f=' . \Illuminate\Support\Facades\Crypt::encrypt($current_folder) : '' }}" class="btn btn-sm btn-danger">
                    <span class="fa fa-trash"></span>&nbsp;&nbsp;Delete file
                  </a>
                  @if(count($files) > 1)
                    <a id="delete-all-log" href="?delall=true{{ ($current_folder) ? '&f=' . \Illuminate\Support\Facades\Crypt::encrypt($current_folder) : '' }}" class="btn btn-sm btn-danger">
                      <span class="fa fa-trash-alt"></span>&nbsp;&nbsp;Delete all files
                    </a>
                  @endif
                @endif
              </div>
              <div class="card">
                @if ($logs === null)
                  <div>
                    Log file >50M, please download it.
                  </div>
                @else
                <div class="table-responsive text-nowrap">
                  <table id="table-log" class="table">
                    <thead>
                      <tr>
                        @if ($standardFormat)
                          <th>Level</th>
                          <th>Context</th>
                          <th>Date</th>
                        @else
                          <th>Line number</th>
                        @endif
                        <th>Content</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                      @foreach($logs as $key => $log)
                      <tr data-display="stack{{{$key}}}">
                        @if ($standardFormat)
                          <td class="nowrap text-{{{$log['level_class']}}}">
                            <span class="fa fa-{{{$log['level_img']}}}" aria-hidden="true"></span>&nbsp;&nbsp;{{$log['level']}}
                          </td>
                          <td class="text">{{$log['context']}}</td>
                        @endif
                        <td class="date">{{{$log['date']}}}</td>
                        <td class="text">
                          @if ($log['stack'])
                            <button type="button" class="btn btn-sm btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#informationModal_{{$key}}">
                            <span class="fa fa-eye"></span>
                          </button>
                          @endif
                          {{{$log['text']}}}
                          @if (isset($log['in_file']))
                            <br/>{{{$log['in_file']}}}
                          @endif
                          @if ($log['stack'])
                            <!-- Modal -->
                            <div class="modal fade" id="informationModal_{{$key}}" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-xl modal-simple modal-pricing">
                                <div class="modal-content p-2 p-md-5">
                                  <div class="modal-body">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    <div class="py-0 rounded-top">
                                      
                                        {{{$log['text']}}}
                                        @if (isset($log['in_file']))
                                          <br/>{{{$log['in_file']}}}
                                        @endif
                                        <pre>
                                        <div id="content-display">
                                          {{{ trim($log['stack']) }}}
                                        </div>
                                      </pre>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <!--/ Modal -->
                          @endif
                        </td>
                      </tr>
                    @endforeach
                    </tbody>
                  </table>
                </div>
                @endif
              </div>
            </div>

            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>

      <div class="layout-overlay layout-menu-toggle"></div>

      <div class="drag-target"></div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/node-waves/node-waves.js"></script>
    <script src="assets/vendor/libs/i18n/i18n.js"></script>
    <script src="assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    <script src="assets/vendor/js/menu.js"></script>

    <script src="assets/js/main.js"></script>
    <script type="text/javascript">
      $(document).ready(function () {
        $('#delete-log, #clean-log, #delete-all-log').click(function () {
          return confirm('Are you sure?');
        });
      });

      $('#table-log').DataTable();
  
    </script>
    <!-- Page JS -->
  </body>
</html>
