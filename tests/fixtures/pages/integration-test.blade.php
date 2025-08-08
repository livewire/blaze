<div class="page">
    <h1>Integration Test Page</h1>
    
    <!-- Pure component should get folded -->
    <x-button>Save Changes</x-button>
    
    <!-- Non-pure component should stay as component tag -->
    <x-impure-button>Cancel</x-impure-button>
    
    <!-- Nested pure components should get folded -->
    <x-card>
        <x-alert message="Success!" />
    </x-card>
</div>