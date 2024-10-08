<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Restaurant::paginate();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'longitude' => 'required|string',
            'latitude' => 'required|string',
        ]);

        return Restaurant::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Restaurant::where('id', $id)->with('menus')->get();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::find($id);
        $restaurant->update($request->all());

        return $restaurant;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return Restaurant::destroy($id);
    }

    public function search($name)
    {
        return Restaurant::where('name', 'like', '%' . $name . '%')->get();
    }

    public function uploadProfileImage(Request $request)
    {
        $restaurant = Restaurant::findOrFail($request->get('restaurant_id'));
        $restaurant->media()->delete();
        $restaurant->addMedia($request->file('image'))->toMediaCollection();
        $restaurant->refresh();

        return $restaurant->getMedia();
    }
}
