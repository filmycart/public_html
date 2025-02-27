<?php
	namespace App\Http\Controllers;
	use Illuminate\Http\Request;
	use App\Models\Contact;
	use Mail;

	class ContactController extends Controller {

		/**
		* Display a listing of the resource.
		*
		* @return \Illuminate\Http\Response
		*/
	    public function index(Request $request)
	    {
	        $sort_search = null;
	        $contacts = Contact::where('created_at', '!=', null)->orderBy('created_at', 'desc');
	        if ($request->has('search')) {
	            $sort_search = $request->search;
	            $contacts->where(function ($q) use ($sort_search) {
	                $q->where('name', 'like', '%'.$sort_search.'%')->orWhere('email', 'like', '%'.$sort_search.'%');
	            });
	        }
	        $contacts = $contacts->paginate(15);
	        return view('backend.contact.index', compact('contacts', 'sort_search'));
	    }

	    /**
	     * Show the form for creating a new resource.
	     *
	     * @return \Illuminate\Http\Response
	     */
	    public function create()
	    {
	        //
	    }

	    /**
	     * Store a newly created resource in storage.
	     *
	     * @param  \Illuminate\Http\Request  $request
	     * @return \Illuminate\Http\Response
	     */
	    public function store(Request $request)
	    {
	        $request->validate([
	            'name'          => 'required',
	            'email'         => 'required|unique:users|email',
	            'phone'         => 'required|unique:users',
	        ]);
	        
	        $response['status'] = 'Error';
	        
	        $user = User::create($request->all());
	        
	        $customer = new Customer;
	        
	        $customer->user_id = $user->id;
	        $customer->save();
	        
	        if (isset($user->id)) {
	            $html = '';
	            $html .= '<option value="">
	                        '. translate("Walk In Customer") .'
	                    </option>';
	            foreach(Customer::all() as $key => $customer){
	                if ($customer->user) {
	                    $html .= '<option value="'.$customer->user->id.'" data-contact="'.$customer->user->email.'">
	                                '.$customer->user->name.'
	                            </option>';
	                }
	            }
	            
	            $response['status'] = 'Success';
	            $response['html'] = $html;
	        }
	        
	        echo json_encode($response);
	    }

	    /**
	     * Display the specified resource.
	     *
	     * @param  int  $id
	     * @return \Illuminate\Http\Response
	     */
	    public function show($id)
	    {
	        //
	    }

	    /**
	     * Show the form for editing the specified resource.
	     *
	     * @param  int  $id
	     * @return \Illuminate\Http\Response
	     */
	    public function edit($id)
	    {
	        //
	    }

	    /**
	     * Update the specified resource in storage.
	     *
	     * @param  \Illuminate\Http\Request  $request
	     * @param  int  $id
	     * @return \Illuminate\Http\Response
	     */
	    public function update(Request $request, $id)
	    {
	        //
	    }

	    /**
	     * Remove the specified resource from storage.
	     *
	     * @param  int  $id
	     * @return \Illuminate\Http\Response
	     */
	    public function destroy($id)
	    {
	        Contact::destroy($id);
	        flash(translate('Contact has been deleted successfully'))->success();
	        return redirect()->route('contacts.index');
	    }
	    
	    public function bulk_contacts_delete(Request $request) {
	        if($request->id) {
	            foreach ($request->id as $contact_id) {
	                $this->destroy($contact_id);
	            }
	        }
	        
	        return 1;
	    }

	    public function login($id)
	    {
	        $user = User::findOrFail(decrypt($id));

	        auth()->login($user, true);

	        return redirect()->route('dashboard');
	    }

	    public function ban($id) {
	        $user = User::findOrFail(decrypt($id));

	        if($user->banned == 1) {
	            $user->banned = 0;
	            flash(translate('Customer UnBanned Successfully'))->success();
	        } else {
	            $user->banned = 1;
	            flash(translate('Customer Banned Successfully'))->success();
	        }

	        $user->save();
	        
	        return back();
	    }

	    // Create Contact Form
	    public function createForm(Request $request) {
	    	return view('frontend.contact');
	    }

	    // Store Contact Form data
	    public function ContactUsForm(Request $request) {

	    	//dd($request);
	        // Form validation
	        $this->validate($request, [
	            'name' => 'required',
	            'email' => 'required|email',
	            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
	            'subject'=>'required',
	            'message' => 'required'
	        ]);

	        //  Store data in database
	        Contact::create($request->all());

	        // Send mail to admin
		    \Mail::send('mail', array(
		        'name' => $request->get('name'),
		        'email' => $request->get('email'),
		        'phone' => $request->get('phone'),
		        'subject' => $request->get('subject'),
		        'user_query' => $request->get('message'),
		    ), function($message) use ($request){
		        $message->from($request->email);
		        $message->to('MAIL_FROM_ADDRESS', 'Admin')->subject($request->get('subject'));
		    });

	        //
	        return back()->with('success', 'We have received your message and would like to thank you for writing to us.');
	    }
	}