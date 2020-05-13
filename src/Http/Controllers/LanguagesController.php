<?php

namespace Day4\SwitchLocale\Http\Controllers;

use Illuminate\Routing\Controller;
use Day4\SwitchLocale\SwitchLocale;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Controllers\DeletesFields;
use Laravel\Nova\Nova;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Actions\Actionable;
use Illuminate\Support\Facades\Cache;
use DB;

class LanguagesController extends Controller
{
    use DeletesFields;

    public function languages()
    {
        $locales = SwitchLocale::getLocales();
        return response()->json([
            'locales' => $locales,
            'allowed' => optional(auth()->user())->allowedAllLocale() ? $locales : optional(auth()->user())->locale
        ]);
    }

    public function saveLocale(Request $request)
    {
        $locale = $request->input("locale");
        $user = auth()->user();
        $user->locale = $locale;
        $user->save();

        app()->setLocale($locale);
        return $locale;
    }

    public function delete(Request $request)
    {
        $locale = Cache::has("locale") ? Cache::get("locale") : app()->getLocale();

        $resourceClass = Nova::resourceForKey($request->get("resourceName"));
        if (!$resourceClass) {
            abort("Missing resource class");
        }

        $modelClass = $resourceClass::$model;
        $resource = $modelClass::find($request->get("resourceId"));
        if (!$resource) {
            abort("Missing resource");
        }

        // If translations count === 1 then forget the model completely
        $translationsCount = count($resource->getTranslations(
            $resource->getTranslatableAttributes()[0]
        ));

        if ($translationsCount > 1 and $resource->forgetAllTranslations($locale)->save()) {
            return response()->json(["status" => true]);
        } elseif ($translationsCount === 1) {
            if (in_array(Actionable::class, class_uses_recursive($resource))) {
                $resource->actions()->delete();
            }

            $resource->delete();

            DB::table('action_events')->insert(
                ActionEvent::forResourceDelete($request->user(), collect([$resource]))
                            ->map->getAttributes()->all()
            );

            return response()->json(["status" => true]);
        }

        abort("Error saving");
    }
}
