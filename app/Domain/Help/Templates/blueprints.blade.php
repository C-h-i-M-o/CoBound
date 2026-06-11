<div class="center padding-lg">

    <div class="row">
        <div class="col-md-12">
            <div style='width:50%' class='svgContainer'>
                {!! file_get_contents(ROOT . '/dist/images/svg/undraw_design_data_khdb.svg') !!}
            </div>
            <h1>{{ __('onboarding.blueprints_title') }}</h1><br />
            <p>{!! __('onboarding.blueprints_description') !!}</p>
            <br /><br />
        </div>
    </div>


    <div class="row">
        <div class="col-md-12">

            <a href="{{ BASE_URL }}/valuecanvas/showCanvas"  class="btn btn-primary">{{ __('buttons.create_project_value_canvas') }}</a><br />

        </div>
    </div>


</div>
