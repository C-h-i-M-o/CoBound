<div class="padding-lg" style="width:1190px;">
    <div class="row">
        <div class="col-md-12">
            <x-global::undrawSvg image="undraw_unexpected-friends_42mc.svg" maxWidth="auto"  maxheight="auto" height="250px" headline=""></x-global::undrawSvg>
        </div>
    </div>
    <div class="row onboarding">
        <div class="col-md-12" style="font-size:var(--font-size-l);">

            <div class="col-md-12" style="font-size:var(--font-size-l);">
                <center>
                    <h1 class="fancyLink">{{ __('support.title_future') }}</h1>
                    <p>{{ __('support.p1') }}</p>
                    <br />
                    <p>{{ __('support.p2') }}</p>
                    <br /> <br />
                </center>

                <h1 class="fancyLink">{{ __('support.title_wont_disappear') }}</h1>
                <div class="tw-flex tw-w-full tw-justify-evenly tw-gap-5">
                    <div class="tw-flex-1" style="border: 1px solid var(--main-border-color); padding:15px; border-radius:var(--box-radius);">
                        <strong style="margin-bottom:5px; display:block;">{{ __('support.years') }}</strong>
                        {{ __('support.years_desc') }}
                    </div>
                    <div class="tw-flex-1" style="border: 1px solid var(--main-border-color); padding:15px; border-radius:var(--box-radius);">
                        <strong style="margin-bottom:5px; display:block;">{{ __('support.funding_label') }}</strong>
                        {{ __('support.funding_desc') }}
                    </div>
                    <div class="tw-flex-1" style="border: 1px solid var(--main-border-color); padding:15px; border-radius:var(--box-radius);">
                        <strong style="margin-bottom:5px; display:block;">{{ __('support.license') }}</strong>
                        {{ __('support.license_desc') }}
                    </div>
                </div>

                <br /><br /><br />
                <h1 class="fancyLink">{{ __('support.title_help') }}</h1>
                <div class="tw-flex tw-w-full tw-justify-evenly tw-gap-5">
                    <div class="tw-flex-1" style="background:var(--header-gradient); color:var(--main-titles-color); padding:15px; border-radius:var(--box-radius);">
                        <strong style="margin-bottom:5px;  display:block; color:var(--main-titles-color);">{{ __('support.sponsorship_title') }}</strong>
                        {{ __('support.sponsorship_desc') }}<br /><br />
                        <a href="https://github.com/sponsors/Leantime" class="btn btn-primary" target="_blank" style="background:var(--main-titles-color); color:var(--accent1);">{{ __('support.sponsorship_btn') }}</a>
                    </div>
                    <div class="tw-flex-1" style="background:var(--header-gradient); color:var(--main-titles-color); padding:15px; border-radius:var(--box-radius);">
                        <strong style="margin-bottom:5px;  display:block; color:var(--main-titles-color);">{{ __('support.plugins_title') }}</strong>
                        {{ __('support.plugins_desc') }}<br /><br />
                        <a href="{{ BASE_URL }}/plugins/marketplace" class="btn btn-primary" style="background:var(--main-titles-color); color:var(--accent1);" target="_blank">{{ __('support.plugins_btn') }}</a>
                    </div>
                </div>

                <br /><br /><br />
                <h1 class="fancyLink">{{ __('support.title_money') }}</h1>
                <div class="tw-flex tw-w-full tw-justify-evenly tw-gap-5">
                    <div class="tw-flex-1" >
                        <div style="background:var(--dropdown-link-hover-bg); padding:15px; border-radius:var(--box-radius);">
                            <small>{{ __('support.funds_from') }}</small><br /><strong style="margin-bottom:5px; display:block;">{{ __('support.gh_sponsors') }}</strong>
                            <ul style="margin-left:15px;">
                                <li>{{ __('support.item_features') }}</li>
                                <li>{{ __('support.item_accessibility') }}</li>
                                <li>{{ __('support.item_community') }}</li>
                                <li>{{ __('support.item_translation') }}</li>
                            </ul>
                        </div>
                        <div style="padding:5px 10px;">
                            <strong><em>{!! __('support.impact_gh') !!}</em></strong>
                        </div>
                    </div>
                    <div class="tw-flex-1" >
                        <div style="background:var(--dropdown-link-hover-bg); padding:15px; border-radius:var(--box-radius);">
                            <small>{{ __('support.funds_from') }}</small><br /> <strong style="margin-bottom:5px;  display:block;">{{ __('support.plugin_sales') }}</strong>
                            <ul style="margin-left:15px;">
                                <li>{{ __('support.item_bugs') }}</li>
                                <li>{{ __('support.item_dev') }}</li>
                                <li>{{ __('support.item_testing') }}</li>
                                <li>{{ __('support.item_docs') }}</li>
                            </ul>
                        </div>
                        <div style="padding:5px 10px;">
                            <strong><em>{!! __('support.impact_plugins') !!}</em></strong>
                        </div>
                    </div>
                    <div class="tw-flex-1" >
                        <div style="background:var(--dropdown-link-hover-bg); padding:15px; border-radius:var(--box-radius);">
                            <small>{{ __('support.funds_from') }}</small><br /><strong style="margin-bottom:5px;  display:block;">{{ __('support.saas_revenue') }}</strong>
                            <ul style="margin-left:15px;">
                                <li>{{ __('support.item_infra') }}</li>
                                <li>{{ __('support.item_hosting') }}</li>
                                <li>{{ __('support.item_tools') }}</li>
                                <li>{{ __('support.item_admin') }}</li>
                            </ul>
                        </div>
                        <div style="padding:5px 10px;">
                            <strong><em>{!! __('support.impact_saas') !!}</em></strong>
                        </div>
                    </div>
                </div>

                <br /><br /><br />
                <h1 class="fancyLink">{{ __('support.title_numbers') }}</h1>
                <div class="tw-flex tw-w-full tw-justify-center tw-gap-4">
                    <div class="tw-text-center tw-flex-1" style="background:#D6F3FF; padding:15px; border-radius:var(--box-radius); ">
                        <span style="color:var(--accent1); font-weight:bold; font-size:var(--font-size-xl);">{{ __('support.num_installs') }}</span>
                        <p>{{ __('support.label_installs') }}</p>
                    </div>
                    <div class="tw-text-center tw-flex-1" style="background:#EBF9FF; padding:15px; border-radius:var(--box-radius); ">
                        <span style="color:var(--accent1); font-weight:bold; font-size:var(--font-size-xl);">{{ __('support.num_bugs') }}</span>
                        <p>{{ __('support.label_bugs') }}</p>
                    </div>
                    <div class="tw-text-center tw-flex-1" style="background:#FEEBF3; padding:15px; border-radius:var(--box-radius); ">
                        <span style="color:var(--accent1); font-weight:bold; font-size:var(--font-size-xl);">{{ __('support.num_languages') }}</span>
                        <p>{{ __('support.label_languages') }}</p>
                    </div>
                    <div class="tw-text-center tw-flex-1" style="background:#FBFDED; padding:15px; border-radius:var(--box-radius); ">
                        <span style="color:var(--accent1); font-weight:bold; font-size:var(--font-size-xl);">{{ __('support.num_sponsorship') }}</span>
                        <p>{{ __('support.label_sponsorship') }}</p>
                    </div>
                </div>

                <br /><br /><br />


                <h1 class="fancyLink">{{ __('support.title_who') }}</h1>

                <div class="tw-flex tw-w-full tw-justify-evenly tw-gap-5">
                    <div class="tw-flex-1" style="background:var(--dropdown-link-hover-bg); padding:15px; border-radius:var(--box-radius);">
                        <img src="{{ BASE_URL }}/dist/images/marcel.png" style="float:right; width:100px; border:none; box-shadow:none; margin-left:10px; margin-bottom:10px;"/>
                        <p><strong style="margin-bottom:5px;  display:block;">{{ __('support.marcel_name') }}</strong>{{ __('support.marcel_desc') }}</p>
                        <br />
                        <p>{{ __('support.marcel_1') }}</p>

                        <p>{{ __('support.marcel_2') }}</p><br />
                        <a href="https://www.linkedin.com/in/marcelfolaron/" target="_blank" ><i class="fa fa-linkedin"></i></a>
                    </div>

                    <div class="tw-flex-1" style="background:var(--dropdown-link-hover-bg); padding:15px; border-radius:var(--box-radius);">
                        <img src="{{ BASE_URL }}/dist/images/gloria.png" style="float:right; width:100px; border:none; box-shadow:none; margin-left:10px; margin-bottom:10px;"/>
                        <p><strong style="margin-bottom:5px;  display:block;">{{ __('support.gloria_name') }}</strong>{{ __('support.gloria_desc') }}</p>
                        <br /><p>{{ __('support.gloria_1') }}</p>
                        <p>{{ __('support.gloria_2') }}</p><br />
                        <a href="https://www.linkedin.com/in/gloriafolaron/" target="_blank" ><i class="fa fa-linkedin"></i></a>
                    </div>
                </div>

                <br /><br />
                <div>
                    <center>
                        <p>{{ __('support.closing') }}</p><br /> <br />
                        <h1 class="fancyLink">{{ __('support.ready_title') }}</h1><p>{{ __('support.ready_desc') }}</p>
                        <br />
                        <div class="tw-text-center">
                            <a href="https://github.com/sponsors/Leantime" class="btn btn-primary btn-lg" target="_blank">{{ __('support.ready_btn') }}</a>
                        </div>
                    </center>
                </div>

                <br />
                <div class="clearall"></div>
            </div>
            <div class="clearall"></div>
        </div>
    </div>
</div>
