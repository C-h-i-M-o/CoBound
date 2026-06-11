<div class="center padding-lg" style="width:800px;">
    <div class="row">
        <div class="col-md-12">
            <x-global::undrawSvg image="undraw_social_serenity_vhix.svg" maxWidth="auto"  maxheight="auto" height="250px" headline="{{ __('headlines.welcome') }}"></x-global::undrawSvg>
        </div>
    </div>
    <div class="row onboarding">
        <div class="col-md-12" style="font-size:var(--font-size-l);">
            <br />
            {!! __('onboarding.dashboard_intro') !!}
            <br /><br />
            <a href="javascript:void(0)" class="btn btn-primary" onclick="leantime.helperController.hideAndKeepHidden('dashboard'); leantime.helperController.startProjectDashboardTour();">{{ __("buttons.lets_go") }} <i class="fa-solid fa-arrow-right"></i></a>
            <div class="clearall"></div>
        </div>
    </div>
</div>
