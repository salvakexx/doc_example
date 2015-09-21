<?php //LARAVEL 4.2
//model example
class Document extends Eloquent
{
    protected $table = 'document';
    //get the documents having delay ending in DAYS_NOTIF_DELAY days
    public function scopeNotificateDelay($model)
    {
        $days_delay=Config::get('constants.DAYS_NOTIF_DELAY');
        $model->select(
            'code_document'
            ,'document.id as id'
            ,'user_id'
            ,'email'
            ,'document.created_at as created_at'
            ,DB::raw('DATE_ADD(document.created_at,INTERVAL document_delay DAY) as date_delay')
        )
            ->leftJoin('users','user_id','=','users.id')
            ->where('delay_notif','=',0)
            ->where('document_delay','!=',0)
            ->havingRaw('date_delay < "'.date('Y-m-d H:i:s',time()+$days_delay*24*60*60).'"');
        return $model;
    }
    //returns already offered products for document
    public function AlreadyOffered()
    {
        $id_document=$this->id;
        $response=Transit::select(array(
            'products.*',
            DB::raw('SUM(count_transit) as count_offered')
        ))
            ->leftJoin('transit_pitch','transit_id','=','transit.id')
            ->leftJoin('products','product_id','=','products.id')
            ->leftJoin('document_product',function($join){
                $join->on('document_product.product_id','=','products.id');
                $join->on('document_product.document_id','=','transit.document_id');
            })
            ->where('transit.document_id','=',$id_document)
            ->groupBy('transit_pitch.product_id')->get();
        return $response;
    }
    //returns url to view document details
    public function UrlTo()
    {
        return URL::to('document/show/' . $this->id);
    }
}
//controller example
class DocumentController extends BackendController{

    public function __construct()
    {
        parent::__construct();
        Asset::add('document-js', URL::to('media/backend/js/document.js'));
    }
    //$id_document = 0 - create; $id_document>0 - edit
    public function getAddEdit($id_document=0)
    {
        $id_document=(int)$id_document;
        $Document=Document::findOrNew($id_document);

        $defaultUser = UserInfo::where('is_default', 1)
            ->where('type_user_info', UserInfo::SELLER)->first();

        $countries = Country::lists('title_ru', 'country_id');
        $countries = ['0' => 'Not Defined'] + $countries;

        return View::make('backend.document.parts.add', compact('countries','Document', 'defaultUser'));
    }
    //ajax document saving with preparing products block to reload
    public function postSaveDocument($id_document)
    {
        $return = array('status' => 0, 'message' => 'System Error');

        if (Request::ajax()) {
            $id_document = (int)$id_document;
            $Document = Document::findOrFail($id_document);

            if ($Document->save()) {

                $Countries = Country::all();
                $Products = $Document->AlreadyOffered();
                $ViewData = array('DocumentProducts' => $Products, 'Countries' => $Countries);

                $return = array('status' => 1, 'message' => 'Success!', 'ProductsBlock' => $ViewData);
            }
        }
        return Response::json($return);
    }
}
//Routes examples
Route::get('/login', function()
{
    $view= View::make('backend.login');
    return $view;
});
Route::group(['before'=>'admin-auth', 'after' => 'view-permission'],function() {
    //put admin routes here
    Route::controller('document', 'DocumentController');
    Route::controller('country','CountryController');
    Route::get('/', function()
    {
        return App::make('DocumentController')->getAddEdit();
    });
});
Route::controller('cron', 'HomeController');