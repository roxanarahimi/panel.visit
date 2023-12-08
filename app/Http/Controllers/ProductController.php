<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Article;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\RelatedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;

//use Redis;
//use Illuminate\Support\Facades\Redis;


class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Product::orderBy('index')->orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total()/$perPage);
            $labels = [];
            for ($i=1; $i <= $pages_count; $i++){
                (array_push($labels,$i));
            }
            return response([
                "data"=>ProductResource::collection($data),
                "pages"=>$pages_count,
                "total"=> $data->total(),
                "labels"=> $labels,
                "title"=> 'محصولات',
                "tooltip_new"=> 'ثبت محصول جدید',
            ], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function latest()
    {

        try {
            $data = Product::all()->sortByDesc('id')->take(4);
            return response(ProductResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function indexSite(Request $request)
    {
        // dd($request->all());
        try {
            $data = Product::orderBy('index')->orderByDesc('id')->whereHas('activeCategory')->with('category')->where('active', 1);
            if ($request['cat'] != '') {
                $data = $data->where('product_category_id', $request['cat']);
            }
            if ($request['stock'] == 'true') {
                $data = $data->where('stock', '>', 0);
            }elseif ($request['stock'== 'limited']){
                $data = $data->where('stock', '>', 0)->where('stock','<',5);
            }
//            if ($request['cat_ids'] != '') {
//                $ids = explode(',', $request['cat_ids']);
//                $data = $data->whereIn('product_category_id', $ids);
//
//            }
            if ($request['off'] == 'true') {
                $data = $data->where('off', '>', 0)->where('stock', '>', 0);

            }
            if ($request['search'] != '') {
                $data = $data->where('title', 'Like', '%' . $request['search'] . '%');

            }
            if ($request['sort'] != '') {
                switch ($request['sort']) {

                    case ('sale'):
                    {
                        $data = $data->orderByDesc('sale');
                        break;
                    }
                    case ('score'):
                    {
                        $data = $data->orderByDesc('score');
                        break;
                    }
                    case ('cheap'):
                    {
                        $data = $data->orderBy('price');
                        break;
                    }
                    case ('expensive'):
                    {
                        $data = $data->orderByDesc('price');
                        break;
                    }
                    case ('view'):
                    {
                        $data = $data->orderByDesc('view');
                        break;
                    }
                    default:
                    {
                        $data = $data->orderByDesc('id');
                        break;
                    }
                }
            }
            if ($request['sale'] == 'true') {
                $data = $data->orderByDesc('sale');

            }
            if ($request['limit'] != '') {
                $data = $data->skip(0)->take($request['limit']);
            }
            $data = $data->get();

            return response(ProductResource::collection($data), 200);
//            return response(new ProductResource($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function stockSite()
    {
        try {
//
            $data = Product::whereHas('activeCategory')->with('category')->where('active', 1)->where('stock', '>', 0)->latest()->get();

            return response(ProductResource::collection($data), 200);
//            return response(new ProductResource($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function latestSite()
    {
        try {
            $data = Product::whereHas('activeCategory')->with('category')->where('active', 1)->take(4)->latest()->get();
            return response(ProductResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function show(Product $product)
    {
        try {

            $sizes = ProductSize::where('product_id', $product['id'])->where('stock', '>', 0)->get(['color_name', 'color_code']);
            $sizes = json_decode($sizes);
//            return $sizes;
            $colors = [];
            foreach ($sizes as $item) {
                array_push($colors, json_encode(['color_name' => $item->color_name, 'color_code' => $item->color_code]));
            }
            return response(['product' => new ProductResource($product), 'colors' => array_unique($colors)], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function getSizes($id, $color)
    {
        try {

            $data = ProductSize::where('product_id', $id)->where('color_name', $color)->where('stock', '>', 0)->get();
            return response($data, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function saveImages($requestImages, $productId)
    {
        try {
            $images = '';
            for ($i = 0; $i < count($requestImages); $i++) {
                if ($requestImages[$i][1]) {
                    $name = 'product_' . $productId . '_' . uniqid() . '.png';
                    $image_path = (new ImageController)->uploadImage($requestImages[$i][1], $name, 'images/');
//                    (new ImageController)->resizeImage('images/', $name);
                    $images = $images . '/' . $image_path . ',';
                } else if ($requestImages[$i][0]) {
                    $images = $images . $requestImages[$i][0] . ',';
                }
            }

            return $images;
        }catch (Exception $exception){
            return $exception;
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:products,title',
            ],
            [
                'title.required' => 'لطفا عنوان را وارد کنید',
                'title.unique' => 'این عنوان قبلا ثبت شده است',
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        try {
            $product = Product::create($request->except('image','related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');
                $product->update(['image' => '/' . $image_path]);

                (new ImageController)->resizeImage('images/products/',$name);
            }
            if ($request['related_products']){
                foreach ($request['related_products'] as $item){
                    RelatedProduct::create([
                        'product_id' => $product['id'],
                        'related_product_id' => $item,
                    ]);
                }
            }
            return response(new ProductResource($product), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:products,title,' . $product['id'],
            ],
            [
                'title.required' => 'لطفا عنوان را وارد کنید',
                'title.unique' => 'این عنوان قبلا ثبت شده است',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        try {
            $product->update($request->except('image','related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');

                if ($product['image']){
                    $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                    if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                    if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
                }

                $product->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/products/',$name);


            }

            $relatedZ = RelatedProduct::where('product_id', $request['id'])->get();
            foreach ($relatedZ as $item){ $item->delete();}

            if ($request['related_products']){
                foreach ($request['related_products'] as $item){
                    RelatedProduct::create([
                        'product_id' => $product['id'],
                        'related_product_id' => $item,
                    ]);
                }
            }

            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function sort(Request $request, Product $product)
    {
//        $validator = Validator::make($request->all('title'),
//            [
//                'title' => 'required|unique:products,title,' . $product['id'],
//            ],
//            [
//                'title.required' => 'لطفا عنوان را وارد کنید',
//                'title.unique' => 'این عنوان قبلا ثبت شده است',
//            ]
//        );
//
//        if ($validator->fails()) {
//            return response()->json($validator->messages(), 422);
//        }
        try {
            $product->update($request->all('index'));
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(Product $product)
    {

        try {
            $relatedZ = RelatedProduct::where('product_id', $product['id'])->get();
            foreach ($relatedZ as $item){ $item->delete();}
            if ($product['image']){
                $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
            }
            $product->delete();
            return response('product deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Product $product)
    {
        try {
            $product->update(['active' => !$product['active']]);
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function updateOrder(Request $request, Product $product )
    {
        try {

            $product->update(['images' => substr($request['images'], 0, -1)]);
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCatPanel($id)
    {
        try {
            $data = Product::orderBy('title')->where('product_category_id', $id)->where('active',1)->get();


            return response(["data"=>ProductResource::collection($data)], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
    public function byCat($id)
    {
        try {
            $data = Product::orderBy('id')->where('product_category_id', $id)->where('active',1)->get();

            foreach ($data as $item){
                $thumb2 = $item->image ? str_replace('.png','_thumb.png', $item->image) : '';
                $item->thumb = $thumb2;
            }
            return response([
                "data"=>$data,

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function fix(Request $request){
        $json = array(
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ با ورمیشل",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پیاز فرانسوی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و قارچ با خامه الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ و سبزیجات الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات و ورمیشل الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ و ذرت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پودر حلیم",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "کتلت و همبرگر",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "تیلیت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سس بشامل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => false, "title" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "سبزی",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "گوشت",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "مرغ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "گوجه تند",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "قارچ و پنیر",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "زعفران"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کاری"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "ماسالا"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "باربیکیو"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی سبزیجات"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی گوجه تند"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی باربیکیو"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک سبزیجات"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک گوجه تند"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک کاری"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی گوجه"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "گوشت"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "سبزی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "کاری 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "برنج 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "لیمو عمانی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "سیر 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پیاز 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "قارچ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "جوجه 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوجه فرنگی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "مرغ 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "بره 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوساله 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک زعفران 3 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "گوساله"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "بره"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "title" => "جو پرک"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "title" => "گندم پرک"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "جو"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "رشته"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خوراک با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خوراک با کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت قیمه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت قورمه سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل سیاه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل قرمز"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل سفید"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "سماق"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "سیر"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "زردچوبه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر سالاد"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر کباب"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر ماست"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر لیمو و فلفل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر دارچین"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "آویشن"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر زنجبیل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر پاپریکا"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر پیاز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "title" => "گوجه و ریحان"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "title" => "قارچ و پنیر"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "ریحان با خامه"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "سس بلونیز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "کاری با خامه"),
            array("type" => "پیاز داغ", "brand" => "الیت", "is_main" => true, "title" => "پیاز داغ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کریمر متوسط"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کریمر بزرگ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کونیگ 90"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کونیگ 170"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کلاسیک 40 عددی عقابی آروما"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کاپوچینو"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "عسلی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کاکائو ذرت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "بالشتی شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "حلقه ای شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کورن فلکس مورنینگ لایت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کورن فلکس اسپشیال سی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 330 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 330 گرمی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "مینی کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "مینی کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی نعنایی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی پرتغالی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "پک کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "پک کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "title" => "پک کانتی با طعم نعنا"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "title" => "پک کانتی با طعم پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "فندقی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "آلبالویی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "موزی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "توت فرنگی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری موزی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری موز"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری زعفران"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری شله زرد"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری فرنی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "نارگیلی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پک 12 عددی شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پک 12 عددی دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی فندق"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی نارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت فندقی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت کاپوچینو"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت شیرنارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت توت فرنگی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت موز"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت هفت لایه فندقی"),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم موزی",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم پرتغال",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم نارگیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم وانیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم شکلات",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "پرلیز",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "پرلیز بلیستر",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => false, "title" => "پرلیز بلیستر مینی",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "دراژه با مغز بادام زمینی کوپا",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب ترش 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی قوطی 24 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "title" => "با طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "title" => "با طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و شوید و طعم هل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و شوید و طعم هل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم پرتغال 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم نارگیل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله دارچین",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله قهوه",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله وانیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله زنجبیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله سبوس دار",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "شکلاتی",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "آلبالو",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "نارگیل",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی کرم دار وانیلی گرد",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی کرم دار وانیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار کاکائویی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار نارگیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار توت فرنگی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار پرتغالی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو توت فرنگی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو نارگیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو کاپوچینو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو موز",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو کاکائویی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کینگ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "بارسلونا",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "بایرن مونیخ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "رئال مادرید",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "لیورپول",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "منچستر یونایتد",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "یوونتوس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "انار",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "انبه",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آلبالو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آلوئه ورا",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آناناس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "بلوبری",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "پرتغال",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "توت فرنگی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "طالبی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "موز",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "هلو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "کوپا کولا",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "شکلاتی",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "وانیلی",),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "سبزی", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی قارچ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "قارچ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "کاری", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "پیتزا", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "میگو", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "مرغ کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی مرغ و کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ با ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "جو با قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "ماکارونی با سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ و ورمیشل", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "جو و قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "دال عدس", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "جو و قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "مرغ", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "بره", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "گوساله", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "زعفران", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 48 عددی", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "ساشه مرغ", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه گوساله", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه پیاز", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه گوجه فرنگی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه زعفران", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوساله 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پیاز 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوجه فرنگی 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "زعفران 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "سبزیجات 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "بوقلمون 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "سبزیجات 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("title" => "گوساله 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("title" => "chocotrips", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "winkers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "trips فندقی", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "trips شیری", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "tickers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "شیری", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("title" => "قهوه", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("title" => "دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "فندقی", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "peralus شیری", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "peralus دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "شکلاتی فندقی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "شکلاتی تلخ", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "شکلاتی بادام زمینی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "فندقی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "تلخ", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "کره بادام زمینی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "چند غله", "type" => "بیسکوئیت", "brand" => "ستاک", "is_main" => false),
            array("title" => "چند غله", "type" => "بیسکوئیت", "brand" => "جمانه", "is_main" => false),
            array("title" => "کرم دار دربیس", "type" => "بیسکوئیت", "brand" => "درنا", "is_main" => false),
            array("title" => "سبوس دار", "type" => "بیسکوئیت", "brand" => "گرجی", "is_main" => false),
            array("title" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "فرخنده", "is_main" => false),
            array("title" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "سلامت", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "سبزی", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "ورمیشل", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
        );
        foreach ($json as $item) {
            Product::create($item);
        }
    }
    public function fix2(Request $request)
    {
        try {
            $dir= "images/".$request['dir'];
            $dirlist = scandir($dir);
            for ($i=2; $i<count($dirlist); $i++){
                (new ImageController)->resizeImage($dir.'/',$dirlist[$i]);
            }
            echo "<pre>",print_r(scandir($dir)),"</pre>";
        }catch (\Exception $exception){
            return $exception;
        }
    }
}
