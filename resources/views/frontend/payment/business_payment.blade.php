@extends('layouts.frontend_layout')

@section('title')
    Amarkrishiponno - Business Seller Payment
@endsection

@section('frontend_content')
    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card p-4" style="background: #f8f9fa;">
                    <h3 class="mb-4">Pay Seller for {{ $business->product_name }}</h3>

                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ url('pay-business') }}" method="POST">
                        @csrf
                        <input type="hidden" name="business_id" value="{{ $business->id }}">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Product</label>
                                <input type="text" class="form-control" value="{{ $business->product_name }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Price per kg (TK)</label>
                                <input type="text" class="form-control" value="{{ $business->price }}" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Quantity (kg)</label>
                                <input type="number" name="quantity" id="quantity" class="form-control"
                                    value="{{ old('quantity', $quantity ?? 1) }}" min="1"
                                    max="{{ $business->product_quantity }}" required>
                                @error('quantity')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Total Amount (TK)</label>
                                <input type="text" id="total_amount" class="form-control" name="amount"
                                    value="{{ $business->price * old('quantity', $quantity ?? 1) }}" readonly required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Your Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ Auth::user()->name ?? '' }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Your Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ Auth::user()->email ?? '' }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}"
                                    required>
                                @error('phone')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Post Code</label>
                                <input type="text" name="post_code" class="form-control" value="{{ old('post_code') }}"
                                    required>
                                @error('post_code')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address') }}"
                                required>
                            @error('address')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label>State</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state') }}" required>
                            @error('state')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label>Note for Seller (optional)</label>
                            <textarea name="description" class="form-control" rows="3">I am paying for {{ $business->product_name }} from {{ $business->name }}.</textarea>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg">Continue to Checkout</button>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card p-4" style="background: #f8f9fa;">
                    <h4 class="mb-3">Seller Summary</h4>
                    <p><strong>Seller:</strong> {{ $business->name }}</p>
                    <p><strong>Phone:</strong> {{ $business->phone }}</p>
                    <p><strong>Email:</strong> {{ $business->email }}</p>
                    <p><strong>Location:</strong> {{ $business->district }}, {{ $business->country }}</p>
                    <p><strong>Stock:</strong> {{ $business->product_quantity }} kg</p>
                    <p><strong>Description:</strong>
                        {{ \Illuminate\Support\Str::limit($business->personal_description, 120) }}</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantity = document.getElementById('quantity');
            const totalInput = document.getElementById('total_amount');
            const price = {{ $business->price }};

            function updateTotal() {
                let qty = parseInt(quantity.value) || 1;
                if (qty < 1) qty = 1;
                if (qty > {{ $business->product_quantity }}) {
                    qty = {{ $business->product_quantity }};
                    quantity.value = qty;
                }
                totalInput.value = qty * price;
            }

            quantity.addEventListener('input', updateTotal);
            updateTotal();
        });
    </script>
@endsection
