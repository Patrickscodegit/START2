<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    // Show all listings
    public function index()
    {
        return view('listings.index', [
            'listings' => Listing::latest()->filter(request(['tag', 'search']))->paginate(4),
        ]);
    }

    // Show single listing
    public function show(Listing $listing)
    {
        return view('listings.show', [
            'listing' => $listing,
        ]);
    }

    // Show Create Form
    public function create()
    {
        return view('listings.create');
    }

    // Store Listing Data

    public function store(Request $request) {
        // Validate request, excluding pictures from this part
        $formFields = $request->validate([
            'title' => 'required',
            'company' => ['required', Rule::unique('listings', 'company')],
            'location' => 'required',
            'website' => 'required',
            'email' => ['required', 'email'],
            'tags' => 'required',
            'description' => 'required',
            'pictures.*' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // Note: 'pictures' validation can still occur here, but it's not included in the $formFields
        ]);

        // Remove 'pictures' from form fields before saving if it exists
        unset($formFields['pictures']);
    
        $formFields['user_id'] = auth()->id();
    
        // Create the listing
        $listing = Listing::create($formFields);
    
        // Handle pictures separately
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $path = $file->store('pictures', 'public');
                // Assuming you have a method in the Listing model to associate pictures
                $listing->images()->create(['image_path' => $path]);
            }
        }
    
        return redirect()->route('listings.show', $listing->id)->with('message', 'Listing created successfully!');
    }
    

    // Show Edit Form
    public function edit(Listing $listing)
    {
        return view('listings.edit', ['listing' => $listing]);
    }

    // Update Listing Data
    public function update(Request $request, Listing $listing) {
        if ($listing->user_id != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }
    
        $formFields = $request->validate([
            'title' => 'required',
            'company' => 'required',
            'location' => 'required',
            'website' => 'required',
            'email' => ['required', 'email'],
            'tags' => 'required',
            'description' => 'required',
            'pictures.*' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

         // Remove 'pictures' from form fields before saving if it exists
         unset($formFields['pictures']);
    
         $formFields['user_id'] = auth()->id();


        // Update the listing with other fields
        $listing->update($formFields);
    
        // Handle deletions of images
        if ($request->has('delete_images')) {
            foreach ($request->input('delete_images') as $imageId) {
                $image = $listing->images()->find($imageId);
                if ($image) {
                    Storage::delete($image->image_path);
                    $image->delete();
                }
            }
        }
    
        // Handle new image uploads
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $path = $file->store('pictures', 'public');
                $listing->images()->create(['image_path' => $path]);
            }
        }
    
        return redirect()->route('listings.show', $listing->id)->with('message', 'Listing updated successfully!');
    }
    
    // Delete Listing
    public function destroy(Listing $listing)
    {
        $this->authorize('delete', $listing);
    
        if ($listing->delete()) {
            return redirect()->route('listings.index')->with('message', 'Listing deleted successfully');
        } else {
            return back()->with('error', 'Unable to delete listing');
        }
    }
    
    

    // Manage Listings

    public function manage()
    {
        if (Gate::allows('viewAny', Listing::class)) {
            // Admin user, fetch all listings
            $listings = Listing::latest()->paginate(4);
        } else {
            // Regular user, fetch listings based on user ID
            $listings = Listing::latest()->where('user_id', auth()->id())->paginate(4);
        }
    
        return view('listings.manage', compact('listings'));
    }
}