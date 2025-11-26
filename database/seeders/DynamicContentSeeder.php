<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DynamicContent;
use Str;

class DynamicContentSeeder extends Seeder
{
    public function run()
    {
        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Terms & Conditions');
        $newContent->title = 'Terms & Conditions';
        $newContent->subtitle = 'Our being able to do what we like best, every pleasure is to be welcomed and every pain';
        $newContent->description = null;
        $newContent->save();

        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Welcome to NRT');
        $newContent->title = 'Welcome to NRT';
        $newContent->description = 'I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences';
        $newContent->save();

        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Using Our Services');
        $newContent->title = 'Using Our Services';
        $newContent->description = 'I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences';
        $newContent->save();

        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Privacy policy');
        $newContent->title = 'Privacy policy';
        $newContent->description = 'I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences';
        $newContent->save();

        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Copyright');
        $newContent->title = 'Copyright';
        $newContent->description = 'I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences';
        $newContent->save();

        $newContent = new DynamicContent;
        $newContent->slug = Str::slug('Terms and Conditions');
        $newContent->title = 'Terms and Conditions';
        $newContent->description = 'I must explain to you how all this mistaken idea of denouncing pleasure and praising pain was born and I will give you a complete account of the system, and expound the actual teachings of the great explorer of the truth, the master-builder of human happiness. No one rejects, dislikes, or avoids pleasure itself, because it is pleasure, but because those who do not know how to pursue pleasure rationally encounter consequences
            <ul>
                <li><i class="fa fa-angle-double-right"></i> ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores </li>
                <li><i class="fa fa-angle-double-right"></i> quas molestias excepturi sint occaecati cupiditate non provident</li>
                <li><i class="fa fa-angle-double-right"></i> Nam libero tempore, cum soluta nobis est eligendi optio cumque</li>
                <li><i class="fa fa-angle-double-right"></i> Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates</li>
                <li><i class="fa fa-angle-double-right"></i> repudiandae sint et molestiae non recusandae itaque earum rerum hic tenetur a sapiente delectus</li>
                <li><i class="fa fa-angle-double-right"></i> ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat</li>
            </ul>
        ';
        $newContent->save();
    }
}
