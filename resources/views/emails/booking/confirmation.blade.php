<x-mail::message>
# Подтверждение бронирования

Уважаемый(ая) {{ $booking->getPrimaryGuest()->first_name }}!

Ваше бронирование успешно оформлено.

**Номер брони:** #{{ $booking->id }}  
**Отель:** {{ $booking->hotel->name }}  
**Категория номера:** {{ $booking->category->name }}  
**Тариф:** {{ $booking->plan->name }}  
**Дата заезда:** {{ $booking->check_in_date->format('d.m.Y') }}  
**Дата выезда:** {{ $booking->check_out_date->format('d.m.Y') }}  
**Количество ночей:** {{ $booking->calculateNights() }}  
**Взрослых:** {{ $booking->adults_count }}  
**Детей:** {{ $booking->children_count }}  
**Итого:** {{ number_format($booking->total_price, 2, ',', ' ') }} руб.
**Комментарий:** {{ $booking->comment || 'не указан' }} 

По всем вопросам обращайтесь:  
{{ $booking->hotel->phone }}  
{{ $booking->hotel->email }}

Спасибо за выбор нашего отеля!

{{ config('app.name') }}
</x-mail::message>