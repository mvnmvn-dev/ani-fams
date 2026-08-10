@extends('layouts.guest')

@section('content')
<style>
    .signature-page {
        min-height: 100vh;
        background: #fbfaf7;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 3rem 1rem;
    }
    .signature-card {
        background: #fff;
        border: 1px solid rgba(11, 28, 48, 0.1);
        border-radius: 1rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        max-width: 600px;
        width: 100%;
        overflow: hidden;
    }
    .signature-header {
        background: linear-gradient(135deg, #142E4C 0%, #020F1C 100%);
        padding: 2rem;
        color: #fff;
        text-align: center;
    }
    .signature-header h1 {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 0.5rem;
    }
    .signature-header p {
        font-size: 0.9rem;
        opacity: 0.8;
        margin: 0;
    }
    .signature-body {
        padding: 2rem;
    }
    .signature-canvas-wrap {
        border: 2px dashed rgba(11, 28, 48, 0.15);
        border-radius: 0.75rem;
        background: #f8f8f8;
        position: relative;
        margin-top: 1.5rem;
    }
    .signature-canvas {
        width: 100%;
        height: 200px;
        cursor: crosshair;
        touch-action: none;
    }
    .signature-btn {
        width: 100%;
        background: #142E4C;
        color: #fff;
        font-weight: 700;
        padding: 0.85rem;
        border-radius: 0.75rem;
        margin-top: 1.5rem;
        transition: background 0.2s;
    }
    .signature-btn:hover {
        background: #020F1C;
    }
    .clear-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(0, 0, 0, 0.05);
        color: #000;
        border-radius: 0.5rem;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .clear-btn:hover {
        background: rgba(0, 0, 0, 0.1);
    }
</style>

<div class="signature-page">
    <div class="signature-card">
        <div class="signature-header">
            <h1>Document Signature Request</h1>
            <p>You have been asked to sign a document by {{ config('app.name') }}.</p>
        </div>
        <div class="signature-body">
            <div style="margin-bottom: 1.5rem; font-size: 0.9rem; color: #333;">
                <p><strong>Hello {{ $request['signer_name'] }},</strong></p>
                <p>Please review and sign the requested document.</p>
                @if(!empty($request['message']))
                    <blockquote style="border-left: 3px solid #142E4C; padding-left: 1rem; margin: 1rem 0; font-style: italic; color: #555;">
                        {{ $request['message'] }}
                    </blockquote>
                @endif
            </div>

            <form action="{{ route('documents.signature.submit', $token) }}" method="POST" id="signature-form">
                @csrf
                <input type="hidden" name="signature_data" id="signature_data">
                
                <label style="font-weight: 700; font-size: 0.85rem; color: #142E4C;">Draw your signature below:</label>
                <div class="signature-canvas-wrap">
                    <button type="button" class="clear-btn" id="clear-signature">Clear</button>
                    <canvas id="signature-pad" class="signature-canvas"></canvas>
                </div>
                
                <button type="submit" class="signature-btn" id="submit-signature">Sign Document</button>
            </form>
            
            <form action="{{ route('documents.signature.decline', $request['id']) }}" method="POST" style="margin-top: 1rem; text-align: center;">
                @csrf
                <button type="submit" style="color: #dc2626; font-size: 0.85rem; font-weight: 600; background: none; border: none; cursor: pointer; text-decoration: underline;">
                    Decline to sign
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var canvas = document.getElementById('signature-pad');
        
        function resizeCanvas() {
            var ratio =  Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
        }
        
        window.onresize = resizeCanvas;
        resizeCanvas();

        var signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)'
        });

        document.getElementById('clear-signature').addEventListener('click', function () {
            signaturePad.clear();
        });

        document.getElementById('signature-form').addEventListener('submit', function (e) {
            if (signaturePad.isEmpty()) {
                e.preventDefault();
                alert("Please provide a signature first.");
            } else {
                document.getElementById('signature_data').value = signaturePad.toDataURL();
            }
        });
    });
</script>
@endsection
