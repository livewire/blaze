<div class="page">
    <h1>Integration Test Page</h1>

    <!-- Foldable component should get folded -->
    <x-button>Save Changes</x-button>

    <!-- Non-foldable component should stay as component tag -->
    <x-unfoldable-button>Cancel</x-unfoldable-button>

    <!-- Nested foldable components should get folded -->
    <x-card>
        <x-alert message="Success!" />
    </x-card>
</div>