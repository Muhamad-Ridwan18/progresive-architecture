@use('App\Support\ContactSubjects')
Pesan baru dari formulir kontak website.

Nama    : {{ $senderName }}
Email   : {{ $senderEmail }}
Telepon : {{ $senderPhone ?: '-' }}
Subjek  : {{ ContactSubjects::label($subjectKey) }}

------------------------------------------------------------
{{ $body }}
------------------------------------------------------------

Anda dapat membalas email ini; balasan akan langsung ke {{ $senderEmail }}.
