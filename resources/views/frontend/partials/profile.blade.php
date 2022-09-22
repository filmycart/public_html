 <ul class="list-inline mb-0 h-100 d-flex justify-content-end align-items-center">
                    @if (get_setting('helpline_number'))
                        <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                            <a href="tel:{{ get_setting('helpline_number') }}" class="text-reset d-inline-block opacity-60 py-2">
                                <i class="la la-phone"></i>
                                <span>{{ translate('Help line')}}</span>  
                                <span>{{ get_setting('helpline_number') }}</span>    
                            </a>
                        </li>
                    @endif
                    @auth
                        @if(isAdmin())
                            <div class="dropdown">
                                <div class="dropbtn">{{ translate('Log In')}}</div>
                                <div class="dropdown-content">
                                     <a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard')}}</a>
                                </div>
                            </div> 
                        @else
                            <div class="dropdown">
                                <div class="dropbtn">{{ translate('My Profile')}}</div>
                                <div class="dropdown-content">
                                    @if (Auth::user()->user_type == 'seller')
                                        <a href="{{ route('seller.dashboard') }}">{{ translate('My Dashboard')}}</a>
                                    @else
                                        <a href="{{ route('dashboard') }}">{{ translate('My Dashboard')}}</a>
                                    @endif
                                    <a href="{{ route('logout') }}">{{ translate('Logout')}}</a>
                                </div>
                            </div>

                            <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0 dropdown">
                                <a class="dropdown-toggle no-arrow text-reset" data-toggle="dropdown" href="javascript:void(0);" role="button" aria-haspopup="false" aria-expanded="false">
                                    <span class="">
                                        <span class="position-relative d-inline-block">
                                            <i class="las la-bell fs-18"></i>
                                            @if(count(Auth::user()->unreadNotifications) > 0)
                                                <span class="badge badge-sm badge-dot badge-circle badge-primary position-absolute absolute-top-right"></span>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg py-0">
                                    <div class="p-3 bg-light border-bottom">
                                        <h6 class="mb-0">{{ translate('Notifications') }}</h6>
                                    </div>
                                    <div class="px-3 c-scrollbar-light overflow-auto " style="max-height:300px;">
                                        <ul class="list-group list-group-flush" >
                                            @forelse(Auth::user()->unreadNotifications as $notification)
                                                <li class="list-group-item">
                                                    @if($notification->type == 'App\Notifications\OrderNotification')
                                                        @if(Auth::user()->user_type == 'customer')
                                                        <a href="{{route('purchase_history.details', encrypt($notification->data['order_id']))}}" class="text-reset">
                                                            <span class="ml-2">
                                                                {{translate('Order code: ')}} {{$notification->data['order_code']}} {{ translate('has been '. ucfirst(str_replace('_', ' ', $notification->data['status'])))}}
                                                            </span>
                                                        </a>
                                                        @elseif (Auth::user()->user_type == 'seller')
                                                            <a href="{{ route('seller.orders.show', encrypt($notification->data['order_id'])) }}" class="text-reset">
                                                                <span class="ml-2">
                                                                    {{translate('Order code: ')}} {{$notification->data['order_code']}} {{ translate('has been '. ucfirst(str_replace('_', ' ', $notification->data['status'])))}}
                                                                </span>
                                                            </a>
                                                        @endif
                                                    @endif
                                                </li>
                                            @empty
                                                <li class="list-group-item">
                                                    <div class="py-4 text-center fs-16">
                                                        {{ translate('No notification found') }}
                                                    </div>
                                                </li>
                                            @endforelse
                                        </ul>
                                    </div>
                                    <div class="text-center border-top">
                                        <a href="{{ route('all-notifications') }}" class="text-reset d-block py-2">
                                            {{translate('View All Notifications')}}
                                        </a>
                                    </div>
                                </div>
                            </li>
                        @endif
                    @else
                        <div class="dropdown">
                            <div class="dropbtn">{{ translate('Login')}}</div>
                            <div class="dropdown-content">
                                <a href="{{ route('user.login') }}">{{ translate('Sign In')}}</a>
                                <a href="{{ route('user.registration') }}">{{ translate('Sign Up')}}</a>
                            </div>
                        </div>
                    @endauth