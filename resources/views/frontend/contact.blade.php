@extends('frontend.layouts.app')
@section('content')
    <!--<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">-->
    <link rel="stylesheet" type="text/css" href="{{ static_asset('assets/css/contact_style.css') }}">
    <section class="pt-4 mb-4">
        <div class="container text-left">
            <div class="row">
                <div class="col-lg-6 text-center text-lg-left">
                    <h1 class="fw-600 h4">Contact Us</h1>
                </div>
                <div class="col-lg-6">
                    <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
                        <li class="breadcrumb-item opacity-50">
                            <a class="text-reset" href="{{ route('home') }}">{{ translate('Home')}}</a>
                        </li>
                        <li class="text-dark fw-600 breadcrumb-item">
                            <a class="text-reset" href="{{ route('terms') }}">"{{ translate('Contact Us') }}"</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card text-left">
                <div class="card-body">
                    <div class="mb-5 text-left">
                        <!-- Success message -->
                        @if(Session::has('success'))
                            <div class="alert alert-success">
                                {{Session::get('success')}}
                            </div>
                        @endif
                        <form action="" method="post" action="{{ route('contact.store') }}">
                            <!-- CROSS Site Request Forgery Protection -->
                            @csrf
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" class="form-control" name="name" id="name" {{ $errors->has('name') ? 'error' : '' }}">
                                <!-- Error -->
                                @if ($errors->has('name'))
                                    <div class="error">
                                        {{ $errors->first('name') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" id="email" {{ $errors->has('email') ? 'error' : '' }}">
                                <!-- Error -->
                                @if ($errors->has('email'))
                                    <div class="error">
                                        {{ $errors->first('email') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" class="form-control" name="phone" id="phone" {{ $errors->has('phone') ? 'error' : '' }}">
                                <!-- Error -->
                                @if ($errors->has('phone'))
                                    <div class="error">
                                        {{ $errors->first('phone') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" class="form-control" name="subject" id="subject" {{ $errors->has('subject') ? 'error' : '' }}">
                                <!-- Error -->
                                @if ($errors->has('subject'))
                                    <div class="error">
                                        {{ $errors->first('subject') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea class="form-control" name="message" id="message" rows="4" {{ $errors->has('subject') ? 'error' : '' }}"></textarea>
                                <!-- Error -->
                                @if ($errors->has('message'))
                                    <div class="error">
                                        {{ $errors->first('message') }}
                                    </div>
                                @endif
                            </div>
                            <input type="submit" name="send" value="Submit" class="btn btn-dark btn-block">
                        </form>
                    </div>
                </div> 
            </div> 
        </div>    
    </section>
@endsection

