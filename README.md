

## About project
Generate excel/csv

## Command

 
``` 

Run - composer require bhagat/excel:dev-master
``` 
## Code
``` 
web.php / Controller
use App\Exports\ExportUser;
Route::get('/excel', function(){
    return Excel::download(new ExportUser, 'users.xlsx');
});

App\Exports\ExportUser.php

    namespace App\Exports;
	use App\Models\User;
	use Bhagat\Excel\Concerns\FromCollection;

	class ExportUser implements FromCollection {
		public function collection()
		{
			return User::select('name','email')->get();
		}
	}
 
``` 