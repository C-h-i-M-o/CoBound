
<div class="center padding-lg" style="width:800px;">
    <div class="row">
        <div class="col-md-12">
            <x-global::undrawSvg
                image="undraw_joyride_re_968t.svg"
                maxWidth="auto"
                headlineSize="var(--font-size-xxxl)"
                maxheight="auto"
                height="250px"
                headline="{{ __('onboarding.project_intro_title') }}"
            ></x-global::undrawSvg>
        </div>
    </div>
    <div class="row onboarding">
        <div class="col-md-12" style="font-size:var(--font-size-l);">
            <br />
            <div id="firstLoginContent">
                <p><br />{!! __('onboarding.project_intro_description') !!}</p><br />
            </div>
            <br /><br />
            <div class="row">
                <div class="col-md-12 tw-text-center">
                    <a href="javascript:void(0)" class="btn btn-secondary" onclick="leantime.helperController.closeModal()">{{ __('buttons.explore_on_my_own') }}</a>
                    <a href="javascript:void(0)" class="btn btn-primary" onclick="leantime.helperController.closeModal(); leantime.helperController.startProjectDashboardTour();">{{ __("buttons.start_tour") }} <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12 tw-text-center">
                    <form hx-post="{{ BASE_URL }}/help/helperModal/dontShowAgain" hx-trigger="change" hx-swap="none">
                        <label class="tw-text-sm tw-mt-sm" >
                            <input type="hidden" name="modalId" value="projectDashboard" />
                            <input type="checkbox" id="dontShowAgain" name="hidePermanently"  style="margin-top:-2px;">
                            {{ __('label.dont_show_again') }}
                        </label>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
